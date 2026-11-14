<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'string', Rule::exists('vehicles', 'id')],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'mobile_number' => ['required', 'string', 'regex:/^(?:\\+63|0)9\\d{9}$/'],
            'pickup_location' => ['required', 'string', 'max:255'],
            'return_location' => ['required', 'string', 'max:255'],
            'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:pickup_date'],
            'return_time' => ['required', 'date_format:H:i'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $vehicleId = $this->input('vehicle_id');
            $vehicle = Vehicle::query()->where('id', $vehicleId)->first();

            if (! $vehicle || $vehicle->status !== Vehicle::STATUS_ACTIVE) {
                $validator->errors()->add('vehicle_id', 'The selected vehicle is not available for booking.');

                return;
            }

            $pickupDate = $this->input('pickup_date');
            $pickupTime = $this->input('pickup_time');
            $returnDate = $this->input('return_date');
            $returnTime = $this->input('return_time');

            if (! $pickupDate || ! $pickupTime || ! $returnDate || ! $returnTime) {
                return;
            }

            $pickupDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$pickupDate} {$pickupTime}", 'Asia/Manila');
            $returnDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$returnDate} {$returnTime}", 'Asia/Manila');

            if ($pickupDateTime->isPast()) {
                $validator->errors()->add('pickup_time', 'Pickup date and time cannot be in the past.');
            }

            if ($returnDateTime->lte($pickupDateTime)) {
                $validator->errors()->add('return_date', 'Return date and time must be later than the pickup date and time.');
            }
        });
    }
}
