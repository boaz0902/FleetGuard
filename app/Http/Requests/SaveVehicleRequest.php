<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');
        $ability = $vehicle instanceof Vehicle ? 'update' : 'create';

        return $this->user()?->can($ability, $vehicle ?? Vehicle::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'brand', 'model', 'category', 'description', 'transmission', 'fuel_type', 'image_path'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '' && in_array($field, ['description', 'image_path'], true)) {
                    $value = null;
                }

                $normalized[$field] = $field === 'category' && $value !== null
                    ? Str::lower($value)
                    : $value;
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $imagePaths = collect(File::files(public_path('images/fleet')))
            ->map(fn (\SplFileInfo $file): string => 'images/fleet/'.$file->getFilename())
            ->merge(collect(Storage::disk('public')->exists('fleet')
                ? Storage::disk('public')->files('fleet')
                : [])->map(fn (string $path): string => 'storage/'.$path))
            ->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:[ -][a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'daily_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'passenger_capacity' => ['required', 'integer', 'min:1', 'max:32'],
            'transmission' => ['required', 'string', 'max:50'],
            'fuel_type' => ['required', 'string', 'max:50'],
            'image_path' => ['nullable', 'string', 'max:255', Rule::in($imagePaths)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=4096,max_height=4096'],
            'status' => ['required', Rule::in([
                Vehicle::STATUS_ACTIVE,
                Vehicle::STATUS_INACTIVE,
                Vehicle::STATUS_MAINTENANCE,
            ])],
        ];
    }
}
