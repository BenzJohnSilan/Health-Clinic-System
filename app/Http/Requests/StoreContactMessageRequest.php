<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the public Contact Us form (landing page #contact).
 * On failure the visitor goes back to the form section with their input and
 * the per-field errors.
 */
class StoreContactMessageRequest extends FormRequest
{
    /** Where a failed validation sends the visitor back to. */
    protected $redirect = '/#contact';

    public function authorize(): bool
    {
        return true; // public form
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name'     => trim((string) $this->input('first_name')),
            'last_name'      => trim((string) $this->input('last_name')),
            'email'          => trim((string) $this->input('email')),
            'contact_number' => trim((string) $this->input('contact_number')),
            'message'        => trim((string) $this->input('message')),
        ]);
    }

    public function rules(): array
    {
        $name = ['required', 'string', 'max:60', "regex:/^[\\pL\\pM][\\pL\\pM\\s.'’-]*$/u"];

        return [
            'first_name'     => $name,
            'last_name'      => $name,
            'email'          => ['required', 'string', 'email:rfc', 'max:150'],
            'contact_number' => [
                'required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-()]*$/',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $digits = strlen(preg_replace('/\D/', '', (string) $value));
                    if ($digits < 7 || $digits > 15) {
                        $fail('Please enter a valid contact number (7 to 15 digits).');
                    }
                },
            ],
            'message'        => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex'     => 'The first name may only contain letters, spaces, periods, apostrophes and hyphens.',
            'last_name.regex'      => 'The last name may only contain letters, spaces, periods, apostrophes and hyphens.',
            'email.email'          => 'Please enter a valid email address.',
            'contact_number.regex' => 'The contact number may only contain digits, spaces, + ( ) and -.',
            'message.min'          => 'Please write a message of at least 10 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name'     => 'first name',
            'last_name'      => 'last name',
            'email'          => 'email address',
            'contact_number' => 'contact number',
        ];
    }
}
