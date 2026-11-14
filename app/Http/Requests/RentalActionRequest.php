<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RentalActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageRental', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
