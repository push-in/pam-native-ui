# Forms and validation

PAM Native UI forms are three pieces that each do one thing:

1. **A typed form object.** A `Pam\Native\Forms\NativeForm` subclass whose
   public properties are the fields and whose attributes are the rules.
2. **Two-way binding.** `p-model="$form->email"` writes each change through
   `NativeForm::set()`, which marks the field dirty and touched and clears its
   previous error.
3. **Display rules.** `Pam\NativeUi\Forms\InteractsWithForm` decides when an
   error is shown and re-renders the component after validation.

## The form

```php
use Pam\Native\Forms\Attributes\Email;
use Pam\Native\Forms\Attributes\MinLength;
use Pam\Native\Forms\Attributes\Required;
use Pam\Native\Forms\NativeForm;
use Pam\NativeUi\Forms\Rules\Accepted;
use Pam\NativeUi\Forms\Rules\Between;
use Pam\NativeUi\Forms\Rules\OneOf;

final class SignUpForm extends NativeForm
{
    #[Required('Tell us your name.')]
    public string $name = '';

    #[Required, Email]
    public string $email = '';

    #[Required, MinLength(8)]
    public string $password = '';

    #[Required, OneOf(['developer', 'designer'])]
    public ?string $role = null;

    #[Between(1, 500)]
    public int $team = 1;

    #[Accepted]
    public bool $terms = false;
}
```

Rules run in declaration order and stop at the first message per field.

| Rule | Package | Passes when |
| --- | --- | --- |
| `Required` | PAM Native | not null, not blank, not an empty array |
| `Email` | PAM Native | a valid address (empty passes) |
| `MinLength(n)`, `MaxLength(n)` | PAM Native | character count (empty passes) |
| `Matches('other')` | PAM Native | equals another field |
| `Accepted` | PAM Native UI | exactly `true` |
| `Between(min, max)` | PAM Native UI | numeric and inside the range (empty passes) |
| `Pattern('/regex/')` | PAM Native UI | the regex matches (empty passes) |
| `OneOf([...])` | PAM Native UI | one of the values (empty passes) |

A rule is any attribute implementing `Pam\Native\Forms\ValidationRule`.

## The component

```php
use Pam\Native\Component;
use Pam\NativeUi\Forms\InteractsWithForm;

final class SignUp extends Component
{
    use InteractsWithForm;

    public SignUpForm $form;

    public function __construct()
    {
        $this->form = new SignUpForm();
    }

    public function save(): void
    {
        if (!$this->submitForm()) {
            return;
        }
        // Send $this->form->values() to your API, then:
        $this->form->succeed();
        $this->touchForm();
    }
}
```

| Method | Use |
| --- | --- |
| `error('field')` | The field's first error, once the field was touched or a submit failed. Bind it to `:error`. |
| `validateField('field')` | Re-validate one field. Bind it to `@blur` for on-blur validation. |
| `submitForm()` | Validate everything; returns false and shows every error when a rule fails. |
| `submitting()` | True between `submitForm()` and `succeed()` / `fail()`. Bind it to the button's `:loading`. |
| `formFailed()` | True after a failed submit. Use it for a `p-form` summary. |
| `touchForm()` | Re-render after you change the form object yourself (`succeed()`, `fail()`, `fill()`). |

Server errors: `$this->form->fail(['email' => 'Already registered.'])` then
`$this->touchForm()`; the message shows under the field.

## The template

```xml
<p-form :error="formFailed() ? 'Fix the highlighted fields and try again.' : null">
    <p-text-field p-model="$form->name" label="Name" required="true" :error="error('name')" @blur="validateField('name')" />
    <p-text-field p-model="$form->email" label="Email" type="email" required="true" :error="error('email')" @blur="validateField('email')" />
    <p-text-field p-model="$form->password" label="Password" type="password" required="true" :error="error('password')" />
    <p-select p-model="$form->role" label="Role" :options="['developer' => 'Developer', 'designer' => 'Designer']" :error="error('role')" />
    <p-stepper p-model="$form->team" label="Team size" :min="1" :max="500" />
    <p-checkbox p-model="$form->terms" label="I accept the terms" :error="error('terms')" />
    <p-btn label="Create account" block="true" :loading="submitting()" @press="save" />
</p-form>
```

Errors are rendered under the field, the field border turns red, and the
message is announced by TalkBack and VoiceOver (live region). The `p-form`
summary is announced after a failed submit.

## Text field types

`type` sets the keyboard, capitalization, autocorrect and autofill hint:
`text`, `email`, `password` (with a Show/Hide toggle), `number`, `decimal`,
`tel`, `url`, `search`, `name`, `username`. `sync` controls how often typing
reaches PHP: `debounced` (default, live validation without a call per
keystroke), `blur`, `submit` or `native` (only when focus leaves or another
control is pressed).
