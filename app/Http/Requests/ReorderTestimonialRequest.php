<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', $this->route('testimonial')) ?? false;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['up', 'down'])],
        ];
    }
}
