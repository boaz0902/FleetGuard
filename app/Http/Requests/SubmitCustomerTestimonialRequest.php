<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitCustomerTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');
        $user = $this->user();

        return $booking instanceof Booking
            && $user?->isCustomer()
            && $user->hasVerifiedEmail()
            && $booking->customer()->where('user_id', $user->id)->exists();
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_content' => ['required', 'string', 'min:20', 'max:2000'],
            'consent' => ['required', 'accepted'],
            'website' => ['nullable', 'prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $booking = $this->route('booking');

            if (! $booking instanceof Booking || $booking->status !== Booking::STATUS_COMPLETED) {
                $validator->errors()->add('booking', 'Only completed bookings can receive a review.');

                return;
            }

            if ($booking->testimonial()->exists()) {
                $validator->errors()->add('booking', 'A review has already been submitted for this booking.');
            }
        });
    }
}
