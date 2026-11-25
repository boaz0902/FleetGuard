<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVehicleUnavailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Vehicle::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'string', 'exists:vehicles,id'],
            'start_datetime' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_datetime' => ['required', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('start_datetime') && ! $validator->errors()->has('start_datetime')) {
                $start = Carbon::createFromFormat(
                    'Y-m-d\TH:i',
                    $this->input('start_datetime'),
                    'Asia/Manila',
                );

                if ($start->lt(Carbon::now('Asia/Manila')->startOfMinute())) {
                    $validator->errors()->add('start_datetime', 'The maintenance start must be now or a future date and time.');
                }
            }

            if ($this->filled('start_datetime') && $this->filled('end_datetime')
                && $this->input('end_datetime') <= $this->input('start_datetime')) {
                $validator->errors()->add('end_datetime', 'The end date and time must be later than the start.');
            }
        });
    }
}
