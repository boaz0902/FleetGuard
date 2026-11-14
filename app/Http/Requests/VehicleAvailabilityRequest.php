<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VehicleAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:pickup_date'],
            'return_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $pickupDate = $this->input('pickup_date');
            $pickupTime = $this->input('pickup_time');
            $returnDate = $this->input('return_date');
            $returnTime = $this->input('return_time');

            if (! $pickupDate || ! $pickupTime || ! $returnDate || ! $returnTime) {
                return;
            }

            $pickup = Carbon::createFromFormat('Y-m-d H:i', "{$pickupDate} {$pickupTime}", 'Asia/Manila');
            $returned = Carbon::createFromFormat('Y-m-d H:i', "{$returnDate} {$returnTime}", 'Asia/Manila');

            if ($pickup->isPast()) {
                $validator->errors()->add('pickup_time', 'Pickup date and time cannot be in the past.');
            }

            if ($returned->lte($pickup)) {
                $validator->errors()->add('return_time', 'Return date and time must be later than pickup.');
            }
        });
    }
}
