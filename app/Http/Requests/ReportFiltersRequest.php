<?php

namespace App\Http\Requests;

use App\Models\AuditLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportFiltersRequest extends FormRequest
{
    private const RANGES = [
        'this_month',
        'last_month',
        'last_30_days',
        'last_90_days',
        'this_year',
        'custom',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    public function rules(): array
    {
        $categories = Vehicle::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        return [
            'range' => ['nullable', 'string', Rule::in(self::RANGES)],
            'start_date' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
            'category' => ['nullable', 'string', 'max:100', Rule::in($categories)],
            'vehicle_id' => ['nullable', 'string', 'max:255', Rule::exists('vehicles', 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
            'maintenance_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vehicleId = $this->input('vehicle_id');
            $category = $this->input('category');

            if (
                $vehicleId !== null
                && $category !== null
                && ! Vehicle::query()->whereKey($vehicleId)->where('category', $category)->exists()
            ) {
                $validator->errors()->add('vehicle_id', 'The selected vehicle does not belong to this category.');
            }

            $range = $this->input('range', 'this_month');

            if ($range !== 'custom' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d', $this->string('start_date')->toString(), config('app.timezone'));
            $end = Carbon::createFromFormat('Y-m-d', $this->string('end_date')->toString(), config('app.timezone'));

            if ($end->lt($start)) {
                $validator->errors()->add('end_date', 'The end date must be on or after the start date.');

                return;
            }

            if ($start->diffInDays($end) > 365) {
                $validator->errors()->add('end_date', 'Custom report ranges cannot exceed 366 calendar days.');
            }
        });
    }

    /**
     * @return array{range:string,start_date:string,end_date:string,category:?string,vehicle_id:?string}
     */
    public function reportFilters(): array
    {
        $validated = $this->validated();
        $range = $validated['range'] ?? 'this_month';
        $now = Carbon::now(config('app.timezone'));

        [$start, $end] = match ($range) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_30_days' => [
                $now->copy()->startOfDay()->subDays(29),
                $now->copy()->startOfDay(),
            ],
            'last_90_days' => [
                $now->copy()->startOfDay()->subDays(89),
                $now->copy()->startOfDay(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
            ],
            'custom' => [
                Carbon::parse($validated['start_date'], config('app.timezone'))->startOfDay(),
                Carbon::parse($validated['end_date'], config('app.timezone'))->startOfDay(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };

        return [
            'range' => $range,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'category' => $validated['category'] ?? null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
        ];
    }
}
