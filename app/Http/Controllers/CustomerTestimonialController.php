<?php

namespace App\Http\Controllers;

use App\Enums\TestimonialSource;
use App\Enums\TestimonialStatus;
use App\Http\Requests\SubmitCustomerTestimonialRequest;
use App\Models\Booking;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerTestimonialController extends Controller
{
    public function create(Request $request, Booking $booking): View|RedirectResponse
    {
        $this->authorize('view', $booking);
        abort_unless($request->user()?->isCustomer(), 403);

        if ($booking->status !== Booking::STATUS_COMPLETED) {
            return redirect()
                ->route('account.bookings')
                ->withErrors(['testimonial' => 'Only completed bookings are eligible for a review.']);
        }

        if ($booking->testimonial()->exists()) {
            return redirect()
                ->route('account.bookings')
                ->with('status', 'A review has already been submitted for this booking.');
        }

        return view('customer.testimonials.create', [
            'booking' => $booking->load('vehicle', 'customer'),
            'publicDisplayName' => $this->publicDisplayName($booking->customer->full_name),
        ]);
    }

    public function store(SubmitCustomerTestimonialRequest $request, Booking $booking): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($booking, $data, $user): void {
            $lockedBooking = Booking::query()
                ->with('customer')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if (! $user instanceof User || $lockedBooking->customer?->user_id !== $user->id) {
                throw new AuthorizationException;
            }

            if ($lockedBooking->status !== Booking::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'booking' => 'Only completed bookings can receive a review.',
                ]);
            }

            if ($lockedBooking->testimonial()->exists()) {
                throw ValidationException::withMessages([
                    'booking' => 'A review has already been submitted for this booking.',
                ]);
            }

            Testimonial::query()->create([
                'customer_display_name' => $this->publicDisplayName($lockedBooking->customer->full_name),
                'review_content' => $data['review_content'],
                'rating' => $data['rating'],
                'review_date' => today(config('app.timezone')),
                'source' => TestimonialSource::VerifiedCustomer,
                'status' => TestimonialStatus::Pending,
                'is_featured' => false,
                'display_order' => 0,
                'user_id' => $user->id,
                'booking_id' => $lockedBooking->id,
                'consent_given' => true,
                'consented_at' => now(config('app.timezone')),
            ]);
        });

        return redirect()
            ->route('account.bookings')
            ->with('status', 'Thank you. Your experience has been submitted for review before it appears publicly.');
    }

    private function publicDisplayName(string $fullName): string
    {
        $nameParts = collect(preg_split('/\s+/', trim($fullName)) ?: [])->filter()->values();
        $firstName = $nameParts->first() ?? 'Customer';
        $lastName = $nameParts->last();

        return $nameParts->count() > 1
            ? $firstName.' '.mb_strtoupper(mb_substr($lastName, 0, 1)).'.'
            : $firstName;
    }
}
