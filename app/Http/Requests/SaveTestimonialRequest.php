<?php

namespace App\Http\Requests;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;

class SaveTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $testimonial = $this->route('testimonial');

        return $testimonial
            ? $this->user()?->can('update', $testimonial) ?? false
            : $this->user()?->can('create', Testimonial::class) ?? false;
    }

    public function rules(): array
    {
        $testimonial = $this->route('testimonial');
        $verifiedCustomerSubmission = $testimonial instanceof Testimonial
            && $testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer;

        return [
            'customer_display_name' => ['required', 'string', 'max:120'],
            'review_content' => ['required', 'string', 'max:3000'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_date' => ['required', 'date', 'before_or_equal:today'],
            'display_order' => ['required', 'integer', 'between:0,999999'],
            'is_featured' => ['required', 'boolean'],
            'profile_image' => $verifiedCustomerSubmission
                ? ['prohibited']
                : [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
                    'dimensions:min_width=80,min_height=80,max_width=1600,max_height=1600',
                ],
            'remove_profile_image' => $verifiedCustomerSubmission ? ['prohibited'] : ['sometimes', 'boolean'],
            'source' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
