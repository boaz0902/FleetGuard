<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && $this->user()?->can('update', $customer) === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['full_name', 'email', 'mobile_number'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer),
            ],
            'mobile_number' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
        ];
    }
}
