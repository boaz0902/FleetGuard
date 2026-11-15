<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelCustomerBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() === true
            && $this->user()->hasVerifiedEmail();
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (is_string($reason)) {
            $this->merge(['reason' => trim($reason)]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:250'],
        ];
    }
}
