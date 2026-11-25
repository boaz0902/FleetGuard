<?php

namespace Tests\Feature;

use App\Enums\TestimonialSource;
use App\Enums\TestimonialStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTestimonialSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private int $bookingSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Vehicle::query()->create([
            'id' => 'testimonial-customer-car',
            'name' => 'Customer Review Car',
            'brand' => 'FleetGuard',
            'model' => 'Test',
            'category' => 'sedan',
            'description' => 'Test vehicle.',
            'daily_rate' => 1800,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => null,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    public function test_guests_cannot_submit_and_customer_must_own_the_completed_booking(): void
    {
        [, $booking] = $this->customerBooking();

        $this->post(route('account.bookings.testimonials.store', $booking), $this->payload())
            ->assertRedirect(route('login'));

        $other = $this->customerUser('other-testimonial@example.com');
        $this->actingAs($other)
            ->post(route('account.bookings.testimonials.store', $booking), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_only_completed_bookings_are_eligible(): void
    {
        foreach ([
            Booking::STATUS_PENDING,
            Booking::STATUS_APPROVED,
            Booking::STATUS_ONGOING,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_REJECTED,
        ] as $status) {
            [$user, $booking] = $this->customerBooking($status);

            $this->actingAs($user)
                ->from(route('account.bookings.testimonials.create', $booking))
                ->post(route('account.bookings.testimonials.store', $booking), $this->payload())
                ->assertSessionHasErrors('booking');
        }

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_completed_booking_can_be_reviewed_once_with_required_consent_and_stays_pending(): void
    {
        [$user, $booking] = $this->customerBooking();

        $this->actingAs($user)
            ->get(route('account.bookings.testimonials.create', $booking))
            ->assertOk()
            ->assertSee('Share your experience')
            ->assertSee('Rina S.')
            ->assertSee('data-testimonial-review-input', false)
            ->assertSee('I consent to public display of my review', false);

        $this->from(route('account.bookings.testimonials.create', $booking))
            ->post(route('account.bookings.testimonials.store', $booking), $this->payload(['consent' => null]))
            ->assertSessionHasErrors('consent');
        $this->assertDatabaseCount('testimonials', 0);

        $this->post(route('account.bookings.testimonials.store', $booking), $this->payload())
            ->assertRedirect(route('account.bookings'))
            ->assertSessionHas('status');

        $testimonial = Testimonial::query()->firstOrFail();
        $this->assertSame($user->id, $testimonial->user_id);
        $this->assertSame($booking->id, $testimonial->booking_id);
        $this->assertSame('Rina S.', $testimonial->customer_display_name);
        $this->assertSame(TestimonialSource::VerifiedCustomer, $testimonial->source);
        $this->assertSame(TestimonialStatus::Pending, $testimonial->status);
        $this->assertTrue($testimonial->consent_given);
        $this->assertNotNull($testimonial->consented_at);

        $this->from(route('account.bookings.testimonials.create', $booking))
            ->post(route('account.bookings.testimonials.store', $booking), $this->payload())
            ->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('testimonials', 1);
        $this->get(route('home'))
            ->assertDontSee('The whole trip felt smooth and well organised.');
        $this->get(route('account.bookings'))
            ->assertSee('Your review is awaiting moderation.');
    }

    public function test_booking_history_shows_submission_action_only_for_eligible_bookings(): void
    {
        [$user, $completed] = $this->customerBooking();
        [, $approved] = $this->customerBooking(Booking::STATUS_APPROVED, $user);

        $this->actingAs($user)
            ->get(route('account.bookings'))
            ->assertOk()
            ->assertSee(route('account.bookings.testimonials.create', $completed), false)
            ->assertSee('Share your experience')
            ->assertDontSee(route('account.bookings.testimonials.create', $approved), false);

        $this->get(route('account.bookings.testimonials.create', $approved))
            ->assertRedirect(route('account.bookings'))
            ->assertSessionHasErrors('testimonial');
    }

    public function test_honeypot_blocks_submission_and_rate_limit_returns_429(): void
    {
        [$user, $booking] = $this->customerBooking();
        $this->actingAs($user);

        $this->from(route('account.bookings.testimonials.create', $booking))
            ->post(route('account.bookings.testimonials.store', $booking), $this->payload(['website' => 'spam.example']))
            ->assertSessionHasErrors('website');
        $this->assertDatabaseCount('testimonials', 0);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('account.bookings.testimonials.store', $booking), $this->payload(['website' => 'bot.example']))
                ->assertSessionHasErrors('website');
        }

        $this->post(route('account.bookings.testimonials.store', $booking), $this->payload(['website' => 'bot.example']))
            ->assertTooManyRequests();

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_admin_can_approve_or_reject_pending_submissions_but_cannot_publish_without_consent(): void
    {
        [$user, $approvedBooking] = $this->customerBooking();
        $this->actingAs($user)->post(route('account.bookings.testimonials.store', $approvedBooking), $this->payload());
        $approvedTestimonial = Testimonial::query()->firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.testimonials.index', [
                'source' => TestimonialSource::VerifiedCustomer->value,
                'status' => TestimonialStatus::Pending->value,
            ]))
            ->assertOk()
            ->assertSee('Rina S.')
            ->assertSee('Customer: Rina Mae Santiago')
            ->assertSee('Booking '.$approvedBooking->booking_reference)
            ->assertSee(route('admin.bookings.show', $approvedBooking), false)
            ->assertSee('Consent: Recorded');

        $this->actingAs($admin)
            ->post(route('admin.testimonials.publish', $approvedTestimonial))
            ->assertRedirect();
        $this->assertSame(TestimonialStatus::Published, $approvedTestimonial->fresh()->status);
        $this->get(route('home'))->assertSee('The whole trip felt smooth and well organised.');

        [, $rejectedBooking] = $this->customerBooking();
        $anotherUser = $rejectedBooking->customer->user;
        $this->actingAs($anotherUser)->post(route('account.bookings.testimonials.store', $rejectedBooking), $this->payload());
        $rejectedTestimonial = Testimonial::query()->where('booking_id', $rejectedBooking->id)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.testimonials.reject', $rejectedTestimonial))
            ->assertRedirect();
        $this->assertSame(TestimonialStatus::Rejected, $rejectedTestimonial->fresh()->status);

        $withoutConsent = Testimonial::factory()->create([
            'source' => TestimonialSource::VerifiedCustomer,
            'status' => TestimonialStatus::Pending,
            'consent_given' => false,
            'consented_at' => null,
        ]);
        $this->actingAs($admin)
            ->from(route('admin.testimonials.index'))
            ->post(route('admin.testimonials.publish', $withoutConsent))
            ->assertRedirect(route('admin.testimonials.index'))
            ->assertSessionHasErrors('testimonial');
        $this->assertSame(TestimonialStatus::Pending, $withoutConsent->fresh()->status);
    }

    public function test_customers_and_staff_cannot_moderate_customer_testimonials(): void
    {
        $testimonial = Testimonial::factory()->create([
            'source' => TestimonialSource::VerifiedCustomer,
            'status' => TestimonialStatus::Pending,
            'consent_given' => true,
            'consented_at' => now(),
        ]);

        foreach ([
            $this->customerUser('moderation-customer@example.com'),
            $this->userWithRole('staff'),
            $this->userWithRole('manager'),
        ] as $user) {
            $this->actingAs($user)
                ->post(route('admin.testimonials.publish', $testimonial))
                ->assertForbidden();
            $this->post(route('admin.testimonials.reject', $testimonial))->assertForbidden();
        }

        $this->assertSame(TestimonialStatus::Pending, $testimonial->fresh()->status);
    }

    public function test_admin_can_edit_pending_customer_content_without_changing_the_consented_name_or_adding_an_image(): void
    {
        [$user, $booking] = $this->customerBooking();
        $this->actingAs($user)->post(route('account.bookings.testimonials.store', $booking), $this->payload());
        $testimonial = Testimonial::query()->firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.testimonials.edit', $testimonial))
            ->assertOk()
            ->assertSee('Verified customer submission')
            ->assertSee('Customer display name')
            ->assertDontSee('Optional profile image');

        $this->put(route('admin.testimonials.update', $testimonial), [
            'customer_display_name' => 'Rina Mae Santiago',
            'review_content' => 'The booking was handled well and the vehicle was ready on time.',
            'rating' => 4,
            'review_date' => today()->toDateString(),
            'display_order' => 0,
            'is_featured' => 0,
        ])->assertRedirect(route('admin.testimonials.edit', $testimonial));

        $testimonial->refresh();
        $this->assertSame('Rina S.', $testimonial->customer_display_name);
        $this->assertSame('The booking was handled well and the vehicle was ready on time.', $testimonial->review_content);
        $this->assertSame(TestimonialStatus::Pending, $testimonial->status);

        $this->put(route('admin.testimonials.update', $testimonial), [
            'customer_display_name' => 'Rina S.',
            'review_content' => $testimonial->review_content,
            'rating' => 4,
            'review_date' => today()->toDateString(),
            'display_order' => 0,
            'is_featured' => 0,
            'profile_image' => 'not-allowed',
        ])->assertSessionHasErrors('profile_image');

        $this->assertNull($testimonial->fresh()->profile_image_path);
    }

    private function customerBooking(string $status = Booking::STATUS_COMPLETED, ?User $user = null): array
    {
        $this->bookingSequence++;
        $user ??= $this->customerUser('testimonial-customer-'.$this->bookingSequence.'@example.com');
        $customer = $user->customer()->first() ?? Customer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Rina Mae Santiago',
            'email' => $user->email,
            'mobile_number' => '09171234567',
            'status' => Customer::STATUS_ACTIVE,
        ]);
        $booking = Booking::query()->create([
            'booking_reference' => sprintf('FG-REVIEW-%04d', $this->bookingSequence),
            'customer_id' => $customer->id,
            'vehicle_id' => 'testimonial-customer-car',
            'pickup_location' => 'Panabo City Center',
            'pickup_datetime' => '2026-10-01 09:00:00',
            'return_location' => 'Panabo City Center',
            'return_datetime' => '2026-10-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 1800,
            'estimated_total' => 3600,
            'customer_notes' => null,
            'status' => $status,
        ]);

        return [$user, $booking];
    }

    private function customerUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->forceFill([
            'role' => 'customer',
            'email_verified_at' => now(),
        ])->save();

        return $user->fresh();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();

        return $user->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'rating' => 5,
            'review_content' => 'The whole trip felt smooth and well organised.',
            'consent' => '1',
        ], $overrides);
    }
}
