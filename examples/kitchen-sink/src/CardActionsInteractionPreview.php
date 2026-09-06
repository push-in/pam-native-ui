<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Material\PBtn;
use Pam\MobileUi\Material\PCardActions;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\UI\Text;

/** Keeps each standalone card-action row interactive without rebuilding its route. */
/** @property (\stdClass&object{selection: string|null}) $state */
final class CardActionsInteractionPreview extends Component
{
    public function __construct(private readonly string $profile = 'pair')
    {
    }

    /** @return array{selection: null|string} */
    protected function initialState(): array
    {
        return ['selection' => null];
    }

    public function render(): Renderable
    {
        $selection = is_string($this->state->selection)
            ? $this->state->selection
            : null;
        $definitions = match ($this->profile) {
            'single' => [['Done', 'tonal', false, 'done']],
            'disabled' => [
                ['Cancel', 'text', true, 'cancel'],
                ['Continue', 'tonal', false, 'continue'],
            ],
            'destructive' => [
                ['Keep', 'text', false, 'keep'],
                ['Delete', 'outlined', false, 'delete'],
            ],
            'long' => [
                ['Review changes', 'text', false, 'review'],
                ['Publish release', 'tonal', false, 'publish'],
            ],
            'three' => [
                ['Back', 'text', false, 'back'],
                ['Save draft', 'text', false, 'draft'],
                ['Send', 'tonal', false, 'send'],
            ],
            default => [
                ['Cancel', 'text', false, 'cancel'],
                ['Continue', 'tonal', false, 'continue'],
            ],
        };

        $actions = [];
        foreach ($definitions as [$label, $variant, $disabled, $value]) {
            $visibleLabel = $selection === $value
                ? $this->selectedLabel($value)
                : $label;
            $actions[] = PBtn::make(
                [
                    'variant' => $variant,
                    ...($value === 'delete' ? ['color' => 'error'] : []),
                    'disabled' => $disabled,
                    'accessibilityLabel' => $label,
                ],
                Text::make($visibleLabel),
            )->onPress(function () use ($value): bool {
                $this->state->selection = $this->state->selection === $value
                    ? null
                    : $value;

                return true;
            });
        }

        return PCardActions::make(
            ['accessibilityLabel' => 'Card actions'],
            ...$actions,
        );
    }

    private function selectedLabel(string $value): string
    {
        return match ($value) {
            'cancel' => 'Cancelled',
            'continue' => 'Continued',
            'done' => 'Done ✓',
            'keep' => 'Kept',
            'delete' => 'Deleted',
            'review' => 'Reviewed',
            'publish' => 'Published',
            'back' => 'Back ✓',
            'draft' => 'Draft saved',
            'send' => 'Sent',
            default => 'Selected',
        };
    }
}
