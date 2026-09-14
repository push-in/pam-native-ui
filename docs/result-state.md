# Result State

Result State presents a result with a semantic icon, title, description and an
optional action directly on the page canvas. It does not add a decorative card.
Use application-specific copy when the user needs a precise next step.

```php
use Pam\MobileUi\Enum\ResultStatus;
use Pam\MobileUi\Generated\MaterialComponentMap;

$result = MaterialComponentMap::TAGS['p-result-state'];
$view = $result::make([
    'status' => ResultStatus::Error->value,
    'title' => 'Report could not be saved',
    'description' => 'Check your connection and try again.',
    'actionLabel' => 'Try again',
])->onPress($retry);
```

The public status codes are sequential integers:

| Enum | Code | Default title |
| --- | --- | --- |
| `ResultStatus::Success` | 1 | All done |
| `ResultStatus::Error` | 2 | Something went wrong |
| `ResultStatus::Warning` | 3 | Review required |
| `ResultStatus::Empty` | 4 | Nothing here yet |

Enum instances are also accepted in PHP. Legacy names `success`, `error`,
`warning` and `empty` remain accepted at the UI boundary; new integrations should
use enum-backed integers. Unknown values throw `InvalidArgumentException` rather
than silently showing success. Omitting status defaults to success.

Error, warning and empty each have their own default description. Empty uses a
neutral icon surface instead of green success styling. `icon`, `title` and
`description` override the defaults. Supplying children replaces the default
content composition.

`loading` (alias `isLoading`) shows native activity feedback, marks the result
busy and prevents the action callback. `disabled` (alias `isDisabled`) also
prevents the action. Explicit canonical values take precedence over aliases.
Progress Button follows the same loading-alias precedence.

Colors come from the active theme; root styles remain overridable. Keep semantic
foreground/background pairs together when customizing themes.
