<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin > Contact Messages > Reply. Access is enforced by the admin route
 * group (auth + verified + role:Admin); this class only validates the input.
 */
class StoreContactReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        $message = $this->route('contactMessage');

        return route('admin.contact-messages.show', $message) . '#reply';
    }

    protected function prepareForValidation(): void
    {
        // No line breaks in a subject (header injection), and trim both fields.
        $this->merge([
            'subject' => trim(preg_replace('/\s+/', ' ', (string) $this->input('subject'))),
            'body'    => trim((string) $this->input('body')),
        ]);
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'body'    => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Please enter a subject.',
            'subject.max'      => 'The subject may not be longer than 150 characters.',
            'body.required'    => 'Please write your reply.',
            'body.min'         => 'Your reply is too short.',
            'body.max'         => 'The reply may not be longer than 5000 characters.',
        ];
    }
}
