<?php

declare(strict_types=1);

/*
 * PAM Native UI test suite: pure helpers, validation rules, and every input
 * component rendered by the PAM Native template renderer with p-model,
 * driven through the same native event closures the device calls, and read
 * back from PHP state. Run: php tests/run.php (PHP 8.5, after composer install).
 */

use Pam\Native\Component;
use Pam\Native\Element;
use Pam\Native\EventKind;
use Pam\Native\Forms\Attributes\Email;
use Pam\Native\Forms\Attributes\Required;
use Pam\Native\Forms\NativeForm;
use Pam\Native\Internal\PamPhpRegistry;
use Pam\Native\Internal\TemplateCompiler;
use Pam\Native\Internal\TemplateRenderer;
use Pam\Native\LanguageVersion;
use Pam\Native\NodeKind;
use Pam\Native\PropKey;
use Pam\NativeUi\Forms\InteractsWithForm;
use Pam\NativeUi\Forms\Rules\Accepted;
use Pam\NativeUi\Forms\Rules\Between;
use Pam\NativeUi\Forms\Rules\OneOf;
use Pam\NativeUi\Forms\Rules\Pattern;
use Pam\NativeUi\Support\Calendar;
use Pam\NativeUi\Support\Fields;
use Pam\NativeUi\Support\Options;

require getenv('PAM_NATIVE_UI_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';

$passed = 0;
$assert = static function (bool $condition, string $message) use (&$passed): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    $passed++;
};

// ---------------------------------------------------------------- helpers
$rows = Options::normalize(['free' => 'Free', 'pro' => 'Pro']);
$assert($rows[1]['value'] === 'pro' && $rows[1]['label'] === 'Pro', 'value => label maps normalize.');
$rows = Options::normalize(['Red', 'Green']);
$assert($rows[0]['value'] === 'Red' && $rows[1]['label'] === 'Green', 'Scalar lists use the label as value.');
$rows = Options::normalize([['value' => 3, 'label' => 'Three', 'disabled' => true]]);
$assert($rows[0]['value'] === 3 && $rows[0]['disabled'], 'Option rows keep their value and flags.');
$assert(count(array_unique(array_column(Options::normalize(['a', 'b', 'c']), 'key'))) === 3, 'Option keys are unique.');
$assert(Options::same('3', 3) && !Options::same(true, 1) && Options::label(['x' => 'Ex'], 'x') === 'Ex', 'Option matching.');

$assert(Fields::keyboard('email') === 'email' && Fields::keyboard('tel') === 'phone', 'Keyboard types follow the field type.');
$assert(Fields::capitalize('email') === 'none' && Fields::autoCorrect('password') === false, 'Credentials are never capitalized or corrected.');
$assert(Fields::boxClass(true, 'Bad', false) === 'p-field-box-focused p-field-box-invalid', 'Focused invalid fields show both states.');
$assert(Fields::counter('héllo', 10) === '5/10', 'Counters count characters, not bytes.');
$assert(Fields::accessibilityLabel('Email', '', true) === 'Email, required', 'Required fields say so to screen readers.');

Calendar::pinToday('2026-11-17');
$weeks = Calendar::weeks('2026-11-01', 0, null, '2026-11-20');
$assert(count($weeks) === 5 && $weeks[0][0]['date'] === '2026-11-01', 'November 2026 starts on a Sunday and spans five weeks.');
$assert($weeks[3][6]['date'] === '2026-11-28' && !$weeks[3][6]['allowed'], 'Days after max are disabled.');
$assert(Calendar::weekdays(1)[0] === 'Mon' && Calendar::shift('2026-01-01', -1) === '2025-12-01', 'Week start and month shifts.');
$assert(Calendar::clamp('2026-11-17', '2026-12-01', null) === '2026-12-01', 'Clamp moves into the allowed range.');

// ------------------------------------------------------------------ rules
final class SignUp extends NativeForm
{
    #[Required]
    #[Email]
    public string $email = '';

    #[Accepted]
    public bool $terms = false;

    #[Between(1, 10)]
    public int $seats = 0;

    #[Pattern('/^[a-z]+$/', 'Lowercase only.')]
    public string $handle = 'OK';

    #[OneOf(['a', 'b'])]
    public ?string $plan = 'c';
}

$form = new SignUp();
$assert(!$form->validate(), 'An empty sign-up form fails.');
$assert($form->error('terms') !== null && $form->error('seats') !== null, 'Accepted and Between report errors.');
$assert($form->error('handle') === 'Lowercase only.' && $form->error('plan') !== null, 'Pattern and OneOf report errors.');
$form->fill(['email' => 'dev@pam.dev', 'terms' => true, 'seats' => 3, 'handle' => 'pam', 'plan' => 'a']);
$assert($form->validate(), 'A complete sign-up form passes.');

// ------------------------------------------------- components on the renderer
$cache = sys_get_temp_dir().'/pam-native-ui-tests-'.getmypid();
PamPhpRegistry::discover(dirname(__DIR__).'/components', $cache);

final class Playground extends Component
{
    use InteractsWithForm;

    public string $text = 'start';
    public string $notes = '';
    public bool $checked = false;
    public bool $toggled = false;
    public ?string $plan = 'free';
    public mixed $country = null;
    public array $tags = [];
    public ?string $date = null;
    public float $volume = 40;
    public int $count = 1;
    public string|int|null $period = 'week';
    public bool $chip = false;
    public string|int|null $tab = 'one';
    public bool $sheet = true;
    public bool $dialog = true;
    public SignUp $form;
    public array $log = [];

    public function __construct()
    {
        $this->form = new SignUp();
    }

    public function changed(mixed $value = null): void
    {
        $this->log[] = $value;
    }
}

$scope = new Playground();
$render = static function (string $template) use ($scope): Element {
    static $sequence = 0;
    $tree = TemplateCompiler::compile($template, 'UiTest'.(++$sequence).'.pam', LanguageVersion::Language1);
    PamPhpRegistry::beginRender();
    $element = TemplateRenderer::render($tree, $scope, []);
    PamPhpRegistry::finishRender();

    return $element;
};
$find = static function (Element $root, callable $match) use (&$find): array {
    $found = $match($root) ? [$root] : [];
    foreach ($root->children() as $child) {
        if ($child instanceof Element) {
            array_push($found, ...$find($child, $match));
        }
    }

    return $found;
};
$byKind = static fn (Element $root, NodeKind $kind): array => $find($root, static fn (Element $e): bool => $e->kind() === $kind);
$byLabel = static fn (Element $root, string $label): ?Element => $find(
    $root,
    static fn (Element $e): bool => ($e->properties()[PropKey::AccessibilityLabel->value] ?? null) === $label,
)[0] ?? null;
$press = static function (?Element $element) use ($assert): void {
    $assert($element !== null && isset($element->events()[EventKind::Press->value]), 'The control must be pressable.');
    ($element->events()[EventKind::Press->value])('');
};

// Text field: value in, keystrokes out, @change, clear, password reveal.
$root = $render('<p-text-field p-model="$text" label="Name" clearable="true" @change="changed" />');
$input = $byKind($root, NodeKind::Input)[0];
$assert(($input->properties()[PropKey::Value->value] ?? null) === 'start', 'p-text-field shows the bound value.');
($input->events()[EventKind::Change->value])('Ada');
$assert($scope->text === 'Ada' && $scope->log === ['Ada'], 'p-text-field writes typing back and emits change.');
$press($byLabel($render('<p-text-field p-model="$text" clearable="true" />'), 'Clear'));
$assert($scope->text === '', 'Clear empties the bound value.');
$root = $render('<p-text-field p-model="$text" type="password" />');
$assert(($byKind($root, NodeKind::Input)[0]->properties()[PropKey::Secure->value] ?? false) === true, 'Passwords are masked.');
$press($byLabel($root, 'Show password'));
$root = $render('<p-text-field p-model="$text" type="password" />');
$assert(($byKind($root, NodeKind::Input)[0]->properties()[PropKey::Secure->value] ?? false) === false, 'Show password reveals it.');

// Textarea.
$input = $byKind($render('<p-textarea p-model="$notes" label="Notes" />'), NodeKind::Input)[0];
($input->events()[EventKind::Change->value])("line 1\nline 2");
$assert($scope->notes === "line 1\nline 2", 'p-textarea writes multi-line text back.');

// Checkbox and switch.
$press($byLabel($render('<p-checkbox p-model="$checked" label="Terms" />'), 'Terms'));
$assert($scope->checked === true, 'p-checkbox toggles the bound bool.');
$press($byLabel($render('<p-switch p-model="$toggled" label="Alerts" />'), 'Alerts'));
$assert($scope->toggled === true, 'p-switch toggles the bound bool.');
$press($byLabel($render('<p-switch p-model="$toggled" label="Alerts" disabled="true" />'), 'Alerts'));
$assert($scope->toggled === true, 'A disabled switch ignores presses.');

// Radio group, segmented, tabs.
$press($byLabel($render('<p-radio-group p-model="$plan" :options="[\'free\' => \'Free\', \'pro\' => \'Pro\']" />'), 'Pro'));
$assert($scope->plan === 'pro', 'p-radio-group writes the chosen value.');
$press($byLabel($render('<p-segmented p-model="$period" :options="[\'day\' => \'Day\', \'week\' => \'Week\']" />'), 'Day'));
$assert($scope->period === 'day', 'p-segmented writes the chosen value.');
$press($byLabel($render('<p-tabs p-model="$tab" :options="[\'one\' => \'One\', \'two\' => \'Two\']" />'), 'Two'));
$assert($scope->tab === 'two', 'p-tabs writes the chosen tab.');

// Select: opens a sheet, picks, closes; multiple toggles values.
$countries = '[\'br\' => \'Brazil\', \'pt\' => \'Portugal\']';
$press($byLabel($render('<p-select p-model="$country" label="Country" :options="'.$countries.'" />'), 'Country, none selected'));
$root = $render('<p-select p-model="$country" label="Country" :options="'.$countries.'" />');
$assert($byKind($root, NodeKind::Modal) !== [], 'p-select opens a bottom sheet.');
$press($byLabel($root, 'Portugal'));
$assert($scope->country === 'pt', 'p-select writes the picked option.');
$root = $render('<p-select p-model="$country" label="Country" :options="'.$countries.'" />');
$assert($byKind($root, NodeKind::Modal) === [] && $byLabel($root, 'Country, Portugal') !== null, 'The sheet closes and the field shows the label.');
$press($byLabel($render('<p-select p-model="$tags" label="Tags" multiple="true" :options="[\'php\' => \'PHP\', \'rust\' => \'Rust\']" />'), 'Tags, none selected'));
$root = $render('<p-select p-model="$tags" label="Tags" multiple="true" :options="[\'php\' => \'PHP\', \'rust\' => \'Rust\']" />');
$press($byLabel($root, 'PHP'));
$press($byLabel($render('<p-select p-model="$tags" label="Tags" multiple="true" :options="[\'php\' => \'PHP\', \'rust\' => \'Rust\']" />'), 'Rust'));
$assert($scope->tags === ['php', 'rust'], 'A multiple select binds an array of values.');

// Date input: calendar sheet, pick a day, min/max respected.
$press($byLabel($render('<p-date-input p-model="$date" label="Start" />'), 'Start, not set'));
$root = $render('<p-date-input p-model="$date" label="Start" />');
$press($byLabel($root, 'Wednesday, November 25, 2026'));
$assert($scope->date === '2026-11-25', 'p-date-input writes the picked ISO date.');
$press($byLabel($render('<p-date-input p-model="$date" label="Start" />'), 'Start, Wed, Nov 25, 2026'));
$root = $render('<p-date-input p-model="$date" label="Start" max="2026-11-26" />');
$day = $byLabel($root, 'Friday, November 27, 2026');
$assert($day !== null && ($day->properties()[PropKey::Enabled->value] ?? true) === false, 'Days after max are disabled.');

// Slider (screen reader adjustments) and stepper.
$slider = $find($render('<p-slider p-model="$volume" label="Volume" :step="5" />'), static fn (Element $e): bool => isset($e->events()[EventKind::AccessibilityAction->value]))[0];
($slider->events()[EventKind::AccessibilityAction->value])('increment');
$assert($scope->volume === 45.0, 'p-slider increments by its step.');
$press($byLabel($render('<p-stepper p-model="$count" label="Seats" :max="2" />'), 'Increase'));
$assert($scope->count === 2, 'p-stepper increments.');
$press($byLabel($render('<p-stepper p-model="$count" label="Seats" :max="2" />'), 'Increase'));
$assert($scope->count === 2, 'p-stepper stops at max.');

// Chip, sheet, dialog.
$press($byLabel($render('<p-chip p-model="$chip" label="Unread" />'), 'Unread'));
$assert($scope->chip === true, 'A filter chip toggles its bool.');
$root = $render('<p-dialog p-model="$dialog" title="Delete?" confirmLabel="Delete" @confirm="changed" />');
$assert($byKind($root, NodeKind::Modal) !== [], 'An open dialog renders a modal.');
$press($find($root, static fn (Element $e): bool => ($e->properties()[PropKey::AccessibilityLabel->value] ?? null) === 'Delete' && $e->kind() === NodeKind::Pressable)[0] ?? null);
$assert($scope->dialog === false && end($scope->log) === null, 'Confirm closes the dialog and emits confirm.');
$root = $render('<p-sheet p-model="$sheet" title="Filters"><Text>Body</Text></p-sheet>');
$sheet = $byKind($root, NodeKind::Modal)[0];
($sheet->events()[EventKind::BottomSheetDismiss->value])('');
$assert($scope->sheet === false, 'Dismissing the sheet writes false back.');

// Forms: p-model into a NativeForm, error shown after a failed submit.
$input = $byKind($render('<p-text-field p-model="$form->email" :error="error(\'email\')" />'), NodeKind::Input)[0];
($input->events()[EventKind::Change->value])('not-an-email');
$assert($scope->form->email === 'not-an-email' && $scope->form->isTouched('email'), 'Typing into a form field touches it.');
$scope->validateField('email');
$assert($scope->error('email') !== null, 'A touched invalid field shows its error.');
$root = $render('<p-text-field p-model="$form->email" :error="error(\'email\')" />');
$assert(
    $find($root, static fn (Element $e): bool => ($e->properties()[PropKey::AccessibilityLiveRegion->value] ?? null) !== null && $e->kind() === NodeKind::Text) !== [],
    'The error is rendered and announced.',
);

// Every component compiles and renders with its defaults.
foreach (['p-btn label="Save"', 'p-icon-btn icon="plus" label="Add"', 'p-card title="T"', 'p-list-item title="Row"', 'p-section title="S"', 'p-divider', 'p-badge label="New"', 'p-avatar name="Ada Lovelace"', 'p-alert text="Hi"', 'p-progress :value="0.4"', 'p-progress', 'p-skeleton :lines="2"', 'p-empty-state title="Empty"', 'p-icon name="check"', 'p-form', 'p-screen title="Home"', 'AppScreen title="Home"', 'p-snackbar', 'p-chip label="A"'] as $tag) {
    $element = $render('<'.$tag.' />');
    $assert($element instanceof Element, "<{$tag}> renders.");
}

foreach (glob($cache.'/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($cache);

echo "PAM Native UI tests passed ({$passed} assertions).\n";
