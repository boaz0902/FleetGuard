<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingStatusTransitionService;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 09:00:00');

        Vehicle::query()->create([
            'id' => 'customer-booking-car',
            'name' => 'Customer Test Car',
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_verified_customer_sees_only_their_own_paginated_bookings_and_status_counts(): void
    {
        $user = $this->customerUser('owner@example.com');
        $customer = $this->linkedCustomer($user);
        $otherUser = $this->customerUser('other@example.com');
        $other = $this->linkedCustomer($otherUser);

        for ($index = 1; $index <= 10; $index++) {
            $this->booking($customer, [
                'booking_reference' => sprintf('FG-OWNER-%02d', $index),
                'status' => $index === 1 ? Booking::STATUS_APPROVED : Booking::STATUS_PENDING,
                'pickup_datetime' => sprintf('2026-12-%02d 09:00:00', $index),
                'return_datetime' => sprintf('2026-12-%02d 18:00:00', $index + 1),
            ]);
        }

        $otherBooking = $this->booking($other, ['booking_reference' => 'FG-PRIVATE']);

        $this->actingAs($user)
            ->get(route('account.bookings', ['page' => 2]))
            ->assertOk()
            ->assertSee('FG-OWNER-02')
            ->assertDontSee('FG-PRIVATE')
            ->assertDontSee('FG-OWNER-10')
            ->assertViewHas('bookings', fn ($bookings): bool => $bookings->total() === 10 && $bookings->perPage() === 8)
            ->assertViewHas('counts', fn ($counts): bool => $counts->get('pending') === 9 && $counts->get('approved') === 1);

        $this->actingAs($user)->get(route('bookings.show', $otherBooking))->assertForbidden();
    }

    public function test_guest_unverified_user_and_admin_cannot_see_customer_booking_history(): void
    {
        $owner = $this->customerUser('owner@example.com');
        $customer = $this->linkedCustomer($owner);
        $booking = $this->booking($customer, ['booking_reference' => 'FG-PRIVATE']);

        $this->get(route('account.bookings'))->assertRedirect(route('login'));

        $unverified = $this->customerUser('unverified@example.com', false);
        $unverifiedCustomer = $this->linkedCustomer($unverified);
        $this->booking($unverifiedCustomer, ['booking_reference' => 'FG-UNVERIFIED']);

        $this->actingAs($unverified)
            ->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Verify your email to view bookings.')
            ->assertSee(route('verification.send'), false)
            ->assertDontSee('FG-UNVERIFIED');

        $admin = $this->customerUser('admin@example.com', true, 'admin');
        $this->actingAs($admin)->get(route('account.bookings'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk();
        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk();
    }

    public function test_empty_state_and_status_filters_render_only_the_customer_records(): void
    {
        $user = $this->customerUser('empty@example.com');
        $customer = $this->linkedCustomer($user);

        $this->actingAs($user)
            ->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('No bookings yet.')
            ->assertSee('Browse the fleet');

        $pending = $this->booking($customer, ['booking_reference' => 'FG-PENDING']);
        $closed = $this->booking($customer, [
            'booking_reference' => 'FG-CLOSED',
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $this->get(route('account.bookings', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('FG-PENDING')
            ->assertDontSee('FG-CLOSED');

        $this->get(route('account.bookings', ['status' => 'closed']))
            ->assertOk()
            ->assertSee('FG-CLOSED')
            ->assertDontSee('FG-PENDING');

        $this->get(route('account.bookings', ['status' => 'invalid']))->assertSessionHasErrors('status');
    }

    public function test_booking_details_show_safe_timeline_and_preserve_the_rate_snapshot(): void
    {
        $user = $this->customerUser('details@example.com');
        $customer = $this->linkedCustomer($user);
        $staff = $this->customerUser('staff-private@example.com', true, 'staff');
        $booking = $this->booking($customer, [
            'status' => Booking::STATUS_COMPLETED,
            'daily_rate_snapshot' => 1200,
            'estimated_total' => 2400,
            'checked_out_at' => '2026-12-01 09:00:00',
            'checked_in_at' => '2026-12-03 09:00:00',
            'admin_remarks' => 'INTERNAL ADMIN REMARK',
            'customer_notes' => 'Please leave the key at reception.',
        ]);
        $booking->auditLogs()->createMany([
            [
                'user_id' => $staff->id,
                'action' => 'booking_approved',
                'changes' => ['from' => 'pending', 'to' => 'approved'],
                'created_at' => '2026-11-16 10:00:00',
            ],
            [
                'user_id' => $staff->id,
                'action' => 'rental_checked_out',
                'changes' => ['from' => 'approved', 'to' => 'ongoing'],
                'created_at' => '2026-12-01 09:00:00',
            ],
            [
                'user_id' => $staff->id,
                'action' => 'rental_checked_in',
                'changes' => ['from' => 'ongoing', 'to' => 'completed'],
                'created_at' => '2026-12-03 09:00:00',
            ],
        ]);
        Vehicle::query()->whereKey($booking->vehicle_id)->update(['daily_rate' => 9000]);

        $this->actingAs($user)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('FG-')
            ->assertSee('Daily rate at booking')
            ->assertSee('₱1,200.00')
            ->assertSee('₱2,400.00')
            ->assertSee('Request submitted')
            ->assertSee('Approved')
            ->assertSee('Checked out')
            ->assertSee('Checked in')
            ->assertSee('Completed')
            ->assertSee('Please leave the key at reception.')
            ->assertDontSee('INTERNAL ADMIN REMARK')
            ->assertDontSee('staff-private@example.com')
            ->assertDontSee($staff->name);

        $this->assertSame('1200.00', $booking->fresh()->daily_rate_snapshot);
        $this->assertSame('2400.00', $booking->fresh()->estimated_total);
    }

    public function test_customer_cancels_pending_booking_with_audited_reason_without_changing_snapshots_or_availability(): void
    {
        $user = $this->customerUser('cancel@example.com');
        $customer = $this->linkedCustomer($user, Customer::STATUS_BLOCKED);
        $booking = $this->booking($customer, [
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_datetime' => '2026-12-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 1500,
            'estimated_total' => 3000,
        ]);
        $original = $booking->only([
            'pickup_datetime',
            'return_datetime',
            'rental_duration_days',
            'daily_rate_snapshot',
            'estimated_total',
        ]);

        $this->actingAs($user)
            ->from(route('bookings.show', $booking))
            ->post(route('account.bookings.cancel', $booking), ['reason' => '  <script>alert(1)</script>  '])
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertEquals($original, $booking->only(array_keys($original)));
        $this->assertNull($booking->admin_remarks);

        $audit = AuditLog::query()
            ->where('subject_type', Booking::class)
            ->where('subject_id', $booking->id)
            ->where('action', 'booking_cancelled_by_customer')
            ->firstOrFail();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame(Booking::STATUS_PENDING, $audit->changes['from']);
        $this->assertSame(Booking::STATUS_CANCELLED, $audit->changes['to']);
        $this->assertSame($customer->id, $audit->changes['customer_id']);
        $this->assertSame($booking->id, $audit->changes['booking_id']);
        $this->assertSame('<script>alert(1)</script>', $audit->changes['reason']);
        $this->assertTrue(app(VehicleAvailabilityService::class)->isAvailable(
            $booking->vehicle,
            $booking->pickup_datetime,
            $booking->return_datetime,
        ));

        $this->actingAs($user)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_cancel_rejects_long_reasons_and_double_submission(): void
    {
        $user = $this->customerUser('reason@example.com');
        $customer = $this->linkedCustomer($user);
        $booking = $this->booking($customer);

        $this->actingAs($user)
            ->post(route('account.bookings.cancel', $booking), ['reason' => str_repeat('x', 251)])
            ->assertSessionHasErrors('reason');
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);

        $this->post(route('account.bookings.cancel', $booking), ['reason' => 'One cancellation.'])
            ->assertSessionHas('status');
        $this->post(route('account.bookings.cancel', $booking), ['reason' => 'Duplicate.'])
            ->assertSessionHasErrors('status');

        $this->assertSame(1, AuditLog::query()
            ->where('subject_type', Booking::class)
            ->where('subject_id', $booking->id)
            ->where('action', 'booking_cancelled_by_customer')
            ->count());
    }

    public function test_only_pending_bookings_can_be_cancelled_by_customers(): void
    {
        $user = $this->customerUser('statuses@example.com');
        $customer = $this->linkedCustomer($user);

        foreach ([
            Booking::STATUS_APPROVED,
            Booking::STATUS_ONGOING,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_REJECTED,
            Booking::STATUS_CANCELLED,
        ] as $status) {
            $booking = $this->booking($customer, ['status' => $status]);

            $this->actingAs($user)
                ->post(route('account.bookings.cancel', $booking))
                ->assertSessionHasErrors('status');

            $this->assertSame($status, $booking->fresh()->status);
        }

        $approved = Booking::query()->where('status', Booking::STATUS_APPROVED)->firstOrFail();
        $this->actingAs($user)
            ->get(route('bookings.show', $approved))
            ->assertOk()
            ->assertSee('Approved bookings cannot be cancelled online.')
            ->assertDontSee('data-cancel-dialog-open', false);
    }

    public function test_transition_service_rechecks_status_under_lock_for_a_stale_customer_request(): void
    {
        $user = $this->customerUser('stale@example.com');
        $customer = $this->linkedCustomer($user);
        $booking = $this->booking($customer);
        $staleBooking = $booking->replicate();
        $staleBooking->id = $booking->id;
        $staleBooking->exists = true;
        $booking->update(['status' => Booking::STATUS_APPROVED]);

        try {
            app(BookingStatusTransitionService::class)->transition(
                $staleBooking,
                Booking::STATUS_CANCELLED,
                $user,
                '',
                'booking_cancelled_by_customer',
                ['customer_id' => $customer->id, 'booking_id' => $booking->id],
            );
            $this->fail('A stale request must not cancel an approved booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Booking::STATUS_APPROVED, $booking->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'booking_cancelled_by_customer',
            'subject_id' => $booking->id,
        ]);
    }

    public function test_account_summary_and_public_navigation_link_to_my_bookings(): void
    {
        $user = $this->customerUser('summary@example.com');
        $customer = $this->linkedCustomer($user);
        $booking = $this->booking($customer, [
            'status' => Booking::STATUS_APPROVED,
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_datetime' => '2026-12-02 09:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('My Bookings')
            ->assertSee(route('account.bookings'), false)
            ->assertSee($booking->vehicle->name)
            ->assertSee(route('bookings.show', $booking), false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('account.bookings'), false);
    }

    public function test_verified_booking_confirmation_includes_details_link_only_when_linked(): void
    {
        $user = $this->customerUser('confirmation@example.com');
        $this->linkedCustomer($user);

        $response = $this->actingAs($user)->postJson('/bookings', [
            'vehicle_id' => 'customer-booking-car',
            'full_name' => 'Confirmation Customer',
            'email' => $user->email,
            'mobile_number' => '09171234567',
            'pickup_location' => 'Panabo City',
            'pickup_date' => '2026-12-10',
            'pickup_time' => '10:00',
            'return_location' => 'Panabo City',
            'return_date' => '2026-12-11',
            'return_time' => '10:00',
        ]);

        $response->assertCreated();
        $booking = Booking::query()->firstOrFail();
        $response->assertJsonPath('booking.details_url', route('bookings.show', $booking));
    }

    private function customerUser(string $email, bool $verified = true, string $role = 'customer'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->forceFill([
            'role' => $role,
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        return $user->fresh();
    }

    private function linkedCustomer(User $user, string $status = Customer::STATUS_ACTIVE): Customer
    {
        return Customer::query()->forceCreate([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'mobile_number' => '09171234567',
            'status' => $status,
        ]);
    }

    private function booking(Customer $customer, array $overrides = []): Booking
    {
        static $sequence = 0;
        $sequence++;

        return Booking::query()->create(array_replace([
            'booking_reference' => sprintf('FG-CUSTOMER-%04d', $sequence),
            'customer_id' => $customer->id,
            'vehicle_id' => 'customer-booking-car',
            'pickup_location' => 'Panabo City Center',
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_location' => 'Panabo City Center',
            'return_datetime' => '2026-12-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 1800,
            'estimated_total' => 3600,
            'customer_notes' => null,
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }
}
