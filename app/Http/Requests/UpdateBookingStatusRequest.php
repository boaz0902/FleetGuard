<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', Booking::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_APPROVED,
                Booking::STATUS_REJECTED,
                Booking::STATUS_CANCELLED,
            ])],
            'admin_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
