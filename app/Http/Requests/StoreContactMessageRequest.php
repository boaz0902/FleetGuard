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
        $name = trim(strip_tags((string) $this->input('name', '')));
        $email = strtolower(trim((string) $this->input('email', '')));
        $phone = trim(strip_tags((string) $this->input('phone', '')));
        $subject = trim(strip_tags((string) $this->input('subject', '')));
        $message = trim(strip_tags((string) $this->input('message', '')));

        $this->merge([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
        ]);
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
