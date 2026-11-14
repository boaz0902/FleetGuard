<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class BlockCustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }

    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && ($this->user()?->can('manageStatus', $customer) ?? false);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
