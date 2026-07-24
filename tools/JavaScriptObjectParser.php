<?php

declare(strict_types=1);

/**
 * Small deterministic parser for the static object-literal subset used by tva().
 *
 * It deliberately does not execute upstream JavaScript. Unknown expressions are
 * skipped and represented as an empty string; quoted strings, template literals
 * and nested objects are preserved.
 */
final class JavaScriptObjectParser
{
    private int $position = 0;

    private readonly int $length;

    public function __construct(private readonly string $source)
    {
        $this->length = strlen($source);
    }

    /** @return array<string, mixed> */
    public function parse(): array
    {
        $this->skipTrivia();
        $value = $this->parseObject();
        $this->skipTrivia();

        if ($this->position !== $this->length) {
            throw new RuntimeException(
                "Unexpected JavaScript content at offset {$this->position}.",
            );
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function parseObject(): array
    {
        $this->expect('{');
        $result = [];

        while (true) {
            $this->skipTrivia();

            if ($this->consume('}')) {
                return $result;
            }

            $key = $this->parseKey();
            $this->skipTrivia();
            $this->expect(':');
            $this->skipTrivia();
            $result[$key] = $this->parseValue();
            $this->skipTrivia();

            if ($this->consume(',')) {
                continue;
            }

            $this->expect('}');

            return $result;
        }
    }

    private function parseValue(): mixed
    {
        $character = $this->peek();

        if ($character === '{') {
            return $this->parseObject();
        }
        if ($character === '[') {
            return $this->parseArray();
        }
        if ($character === "'" || $character === '"') {
            return $this->parseQuotedString($character);
        }
        if ($character === '`') {
            return $this->parseTemplateLiteral();
        }
        if (
            $character === '-'
            || $character === '+'
            || $character === '.'
            || ctype_digit($character)
        ) {
            return $this->parseNumber();
        }

        $identifier = $this->readIdentifier();

        return match ($identifier) {
            'true' => true,
            'false' => false,
            'null', 'undefined' => null,
            default => $this->skipExpression($identifier),
        };
    }

    /** @return list<mixed> */
    private function parseArray(): array
    {
        $this->expect('[');
        $result = [];

        while (true) {
            $this->skipTrivia();

            if ($this->consume(']')) {
                return $result;
            }

            $result[] = $this->parseValue();
            $this->skipTrivia();

            if ($this->consume(',')) {
                continue;
            }

            $this->expect(']');

            return $result;
        }
    }

    private function parseKey(): string
    {
        $character = $this->peek();

        if ($character === "'" || $character === '"') {
            return $this->parseQuotedString($character);
        }

        $key = $this->readIdentifier();

        if ($key === '') {
            throw new RuntimeException(
                "Expected JavaScript object key at offset {$this->position}.",
            );
        }

        return $key;
    }

    private function parseQuotedString(string $quote): string
    {
        $this->expect($quote);
        $result = '';

        while ($this->position < $this->length) {
            $character = $this->source[$this->position++];

            if ($character === $quote) {
                return $result;
            }

            if ($character === '\\') {
                if ($this->position >= $this->length) {
                    break;
                }
                $escaped = $this->source[$this->position++];
                $result .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    default => $escaped,
                };
                continue;
            }

            $result .= $character;
        }

        throw new RuntimeException('Unterminated JavaScript string literal.');
    }

    private function parseTemplateLiteral(): string
    {
        $this->expect('`');
        $result = '';

        while ($this->position < $this->length) {
            $character = $this->source[$this->position++];

            if ($character === '`') {
                return trim(preg_replace('/\s+/', ' ', $result) ?? $result);
            }

            if ($character === '\\') {
                if ($this->position < $this->length) {
                    $result .= $this->source[$this->position++];
                }
                continue;
            }

            if (
                $character === '$'
                && $this->position < $this->length
                && $this->source[$this->position] === '{'
            ) {
                $this->position++;
                $this->skipBalancedExpression();
                continue;
            }

            $result .= $character;
        }

        throw new RuntimeException('Unterminated JavaScript template literal.');
    }

    private function skipBalancedExpression(): void
    {
        $depth = 1;
        $quote = null;
        $escaped = false;

        while ($this->position < $this->length && $depth > 0) {
            $character = $this->source[$this->position++];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;
            }
        }

        if ($depth !== 0) {
            throw new RuntimeException('Unterminated template expression.');
        }
    }

    private function parseNumber(): int|float
    {
        $start = $this->position;

        while ($this->position < $this->length) {
            $character = $this->source[$this->position];
            if (
                !ctype_digit($character)
                && !in_array($character, ['-', '+', '.', 'e', 'E'], true)
            ) {
                break;
            }
            $this->position++;
        }

        $number = substr($this->source, $start, $this->position - $start);

        return str_contains($number, '.') || stripos($number, 'e') !== false
            ? (float) $number
            : (int) $number;
    }

    private function readIdentifier(): string
    {
        $start = $this->position;

        while ($this->position < $this->length) {
            $character = $this->source[$this->position];
            if (
                !ctype_alnum($character)
                && $character !== '_'
                && $character !== '$'
                && $character !== '-'
            ) {
                break;
            }
            $this->position++;
        }

        return substr($this->source, $start, $this->position - $start);
    }

    private function skipExpression(string $prefix): string
    {
        $depth = 0;
        $quote = null;
        $escaped = false;

        while ($this->position < $this->length) {
            $character = $this->source[$this->position];

            if ($quote !== null) {
                $this->position++;
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $this->position++;
                continue;
            }
            if (in_array($character, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($character, [')', ']'], true)) {
                $depth--;
            } elseif ($character === '}' && $depth === 0) {
                break;
            } elseif ($character === '}' && $depth > 0) {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                break;
            }

            $this->position++;
        }

        // Native baseStyle variables in upstream are intentionally empty.
        return $prefix === 'baseStyle' ? '' : '';
    }

    private function skipTrivia(): void
    {
        while ($this->position < $this->length) {
            $character = $this->source[$this->position];

            if (ctype_space($character)) {
                $this->position++;
                continue;
            }

            if (
                $character === '/'
                && ($this->source[$this->position + 1] ?? null) === '/'
            ) {
                $this->position += 2;
                while (
                    $this->position < $this->length
                    && !in_array($this->source[$this->position], ["\n", "\r"], true)
                ) {
                    $this->position++;
                }
                continue;
            }

            if (
                $character === '/'
                && ($this->source[$this->position + 1] ?? null) === '*'
            ) {
                $end = strpos($this->source, '*/', $this->position + 2);
                if ($end === false) {
                    throw new RuntimeException('Unterminated JavaScript block comment.');
                }
                $this->position = $end + 2;
                continue;
            }

            break;
        }
    }

    private function peek(): string
    {
        if ($this->position >= $this->length) {
            throw new RuntimeException('Unexpected end of JavaScript object literal.');
        }

        return $this->source[$this->position];
    }

    private function expect(string $character): void
    {
        if (!$this->consume($character)) {
            throw new RuntimeException(
                "Expected {$character} at offset {$this->position}.",
            );
        }
    }

    private function consume(string $character): bool
    {
        if (($this->source[$this->position] ?? null) !== $character) {
            return false;
        }

        $this->position++;

        return true;
    }
}
