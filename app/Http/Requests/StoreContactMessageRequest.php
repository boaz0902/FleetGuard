<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = static function (mixed $value): mixed {
            if (! is_string($value) && ! is_numeric($value)) {
                return $value;
            }

            return trim(strip_tags((string) $value));
        };

        $name = $clean($this->input('name', ''));
        $email = $clean($this->input('email', ''));
        $phone = $clean($this->input('phone', ''));
        $subject = $clean($this->input('subject', ''));
        $message = $clean($this->input('message', ''));

        $this->merge([
            'name' => $name,
            'email' => is_string($email) ? strtolower($email) : $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('home').'#contact';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', Rule::in([
                'General question',
                'Booking question',
                'Vehicle question',
                'Feedback',
                'Other',
            ])],
            'message' => ['required', 'string', 'max:2000'],
            'submitted_at' => ['required', 'numeric'],
            'honeypot' => ['prohibited'],
        ];
    }
}
