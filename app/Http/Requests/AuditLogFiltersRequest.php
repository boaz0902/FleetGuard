<?php

namespace App\Http\Requests;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AuditLogFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_with:start_date', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('start_date');
            $end = $this->input('end_date');

            if (
                ! is_string($start)
                || ! is_string($end)
                || $validator->errors()->has('start_date')
                || $validator->errors()->has('end_date')
            ) {
                return;
            }

            $startDate = Carbon::createFromFormat('!Y-m-d', $start, config('app.timezone'));
            $endDate = Carbon::createFromFormat('!Y-m-d', $end, config('app.timezone'));

            if ($endDate->lt($startDate)) {
                $validator->errors()->add('end_date', 'The end date must be on or after the start date.');

                return;
            }

            if ($startDate->diffInDays($endDate) > 365) {
                $validator->errors()->add('end_date', 'Audit log date ranges cannot exceed 366 calendar days.');
            }
        });
    }

    /**
     * @return array{search?: string, action?: string, actor?: int|string, start_date?: string, end_date?: string}
     */
    public function filters(): array
    {
        return $this->validated();
    }
}
