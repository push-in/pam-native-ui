<?php

declare(strict_types=1);

namespace Pam\NativeUi\Forms;

use LogicException;
use Pam\Native\Attributes\Action;
use Pam\Native\Forms\FormStatus;
use Pam\Native\Forms\NativeForm;

/**
 * Glue between a component and its form object, so templates can show
 * errors and validate fields without PHP in the markup:
 *
 *     <p-text-field p-model="$form->email" :error="error('email')" @blur="validateField('email')" />
 *     <p-btn label="Create account" :loading="submitting()" @press="save" />
 *
 * The component keeps the form in a public `$form` property (a
 * {@see NativeForm} subclass with rule attributes) or overrides {@see form()}.
 */
trait InteractsWithForm
{
    protected function form(): NativeForm
    {
        $form = property_exists($this, 'form') ? $this->form : null;
        if (!$form instanceof NativeForm) {
            throw new LogicException(static::class.' uses InteractsWithForm without a NativeForm in $form.');
        }

        return $form;
    }

    /** First error of a field, shown only once the field was touched or a submit failed. */
    public function error(string $field): ?string
    {
        $form = $this->form();
        if (!$form->isTouched($field) && $form->status() !== FormStatus::Failure) {
            return null;
        }

        return $form->error($field);
    }

    public function submitting(): bool
    {
        return $this->form()->status() === FormStatus::Submitting;
    }

    public function formFailed(): bool
    {
        return $this->form()->status() === FormStatus::Failure;
    }

    /** Re-validates one field (wire it to `@blur`). */
    #[Action]
    public function validateField(string $field): void
    {
        $this->form()->validate($field);
        $this->touchForm();
    }

    /**
     * Validates every field and moves the form to Submitting. Returns false
     * (and marks the form failed, showing every error) when a rule fails.
     */
    protected function submitForm(): bool
    {
        $valid = $this->form()->beginSubmit();
        $this->touchForm();

        return $valid;
    }

    /** Re-renders the component after the form object changed in place. */
    protected function touchForm(): void
    {
        if (property_exists($this, 'form')) {
            $form = $this->form;
            $this->form = $form;
        }
        $this->__pamNotifyUpdated('form');
        $this->__pamFlushChanges();
    }
}
