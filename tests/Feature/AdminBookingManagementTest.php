<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Vehicle::query()->create([
            'id' => 'toyota-vios',
            'name' => 'Toyota Vios',
            'brand' => 'Toyota',
            'model' => 'Vios',
            'category' => 'sedan',
            'description' => 'Compact sedan.',
            'daily_rate' => 2500,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    public function test_authorized_review_roles_can_search_by_customer_phone(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $booking = $this->booking([
            'customer_name' => 'Private Customer',
            'phone' => '09171234567',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.index', ['search' => '09171234567']))
            ->assertOk()
            ->assertSee($booking->booking_reference);

        $this->actingAs($manager)
            ->get(route('admin.bookings.index', ['search' => '09171234567']))
            ->assertOk()
            ->assertSee($booking->booking_reference);
    }

    public function test_booking_list_paginates_newest_first_and_preserves_filters(): void
    {
        $manager = $this->userWithRole('manager');

        for ($index = 1; $index <= 26; $index++) {
            $this->booking([
                'reference' => sprintf('FG-PAGE%04d', $index),
                'customer_name' => 'Alice Example',
                'status' => Booking::STATUS_PENDING,
                'pickup' => '2026-12-01 09:00:00',
            ]);
        }

        $response = $this->actingAs($manager)->get(route('admin.bookings.index', [
            'search' => 'Alice',
            'status' => Booking::STATUS_PENDING,
            'vehicle_id' => 'toyota-vios',
            'pickup_from' => '2026-12-01',
            'pickup_to' => '2026-12-01',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('FG-PAGE0001')
            ->assertDontSee('FG-PAGE0002')
            ->assertSee('search=Alice', false)
            ->assertSee('status=pending', false)
            ->assertSee('vehicle_id=toyota-vios', false)
            ->assertSee('pickup_from=2026-12-01', false)
            ->assertSee('pickup_to=2026-12-01', false)
            ->assertSee('26 results')
            ->assertSee('Duration')
            ->assertSee('Created');
    }

    public function test_authorized_admin_can_review_full_details_and_booking_audit_history(): void
    {
        $manager = $this->userWithRole('manager');
        $booking = $this->booking([
            'customer_name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'phone' => '09171112222',
        ]);
        $audit = $booking->auditLogs()->create([
            'user_id' => $manager->id,
            'action' => 'booking_approved',
            'changes' => [
                'from' => Booking::STATUS_PENDING,
                'to' => Booking::STATUS_APPROVED,
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee($booking->booking_reference)
            ->assertSee('Jane Customer')
            ->assertSee('09171112222')
            ->assertSee('jane@example.com')
            ->assertSee('Toyota Vios')
            ->assertSee('Sedan')
            ->assertSee('Panabo City')
            ->assertSee('₱2,500.00')
            ->assertSee('₱5,000.00')
            ->assertSee('Booking history')
            ->assertSee('Reservation approved')
            ->assertSee($manager->name)
            ->assertSee(route('admin.bookings.status', $booking), false);

        $this->assertSame(Booking::class, $audit->fresh()->subject_type);
    }

    public function test_admin_actions_follow_existing_transitions_and_create_audit_entries(): void
    {
        $admin = $this->userWithRole('admin');
        $booking = $this->booking();

        $this->actingAs($admin)
            ->from(route('admin.bookings.show', $booking))
            ->patch(route('admin.bookings.status', $booking), [
                'status' => Booking::STATUS_APPROVED,
                'admin_remarks' => 'Availability confirmed.',
            ])
            ->assertRedirect(route('admin.bookings.show', $booking));

        $this->assertSame(Booking::STATUS_APPROVED, $booking->fresh()->status);
        $this->assertSame('Availability confirmed.', $booking->fresh()->admin_remarks);
        $this->assertAuditTransition($booking, 'booking_approved', Booking::STATUS_PENDING, Booking::STATUS_APPROVED, $admin);

        $this->from(route('admin.bookings.show', $booking))
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_ONGOING])
            ->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertAuditTransition($booking, 'booking_marked_ongoing', Booking::STATUS_APPROVED, Booking::STATUS_ONGOING, $admin);
        $this->assertSame('Availability confirmed.', $booking->fresh()->admin_remarks);

        $this->from(route('admin.bookings.show', $booking))
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_COMPLETED])
            ->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertAuditTransition($booking, 'booking_marked_completed', Booking::STATUS_ONGOING, Booking::STATUS_COMPLETED, $admin);
        $this->assertSame(Booking::STATUS_COMPLETED, $booking->fresh()->status);
    }

    public function test_pending_booking_can_be_rejected_or_cancelled_and_reason_is_preserved_in_history(): void
    {
        $manager = $this->userWithRole('manager');
        $rejectedBooking = $this->booking();

        $this->actingAs($manager)
            ->from(route('admin.bookings.show', $rejectedBooking))
            ->patch(route('admin.bookings.status', $rejectedBooking), [
                'status' => Booking::STATUS_REJECTED,
                'admin_remarks' => 'Requested dates cannot be accommodated.',
            ])
            ->assertRedirect(route('admin.bookings.show', $rejectedBooking));

        $rejectionLog = $rejectedBooking->auditLogs()->latest('id')->firstOrFail();
        $this->assertSame('booking_rejected', $rejectionLog->action);
        $this->assertSame('Requested dates cannot be accommodated.', $rejectionLog->changes['reason']);
        $this->assertSame('Requested dates cannot be accommodated.', $rejectedBooking->fresh()->admin_remarks);

        $cancelledBooking = $this->booking([
            'pickup' => '2026-12-10 09:00:00',
            'reference' => 'FG-CANCEL01',
        ]);
        $this->from(route('admin.bookings.show', $cancelledBooking))
            ->patch(route('admin.bookings.status', $cancelledBooking), [
            'status' => Booking::STATUS_CANCELLED,
            'admin_remarks' => 'Customer requested cancellation.',
        ])->assertRedirect(route('admin.bookings.show', $cancelledBooking));

        $this->assertSame(Booking::STATUS_CANCELLED, $cancelledBooking->fresh()->status);
        $this->assertDatabaseHas('bookings', ['id' => $cancelledBooking->id]);
        $this->assertAuditTransition($cancelledBooking, 'booking_cancelled', Booking::STATUS_PENDING, Booking::STATUS_CANCELLED, $manager);
    }

    public function test_invalid_or_skipped_transitions_and_notes_are_rejected_without_audit_changes(): void
    {
        $admin = $this->userWithRole('admin');
        $booking = $this->booking();

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_ONGOING])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $booking->update(['status' => Booking::STATUS_APPROVED]);
        $this->patchJson(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_REJECTED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson(route('admin.bookings.status', $booking), [
            'status' => Booking::STATUS_APPROVED,
            'admin_remarks' => str_repeat('x', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors('admin_remarks');

        $this->assertSame(Booking::STATUS_APPROVED, $booking->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_staff_and_customers_cannot_open_admin_details_or_change_booking_status(): void
    {
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('customer');
        $booking = $this->booking();

        $this->actingAs($staff)
            ->get(route('admin.bookings.show', $booking))
            ->assertForbidden();
        $this->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.bookings.show', $booking))
            ->assertForbidden();
        $this->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertForbidden();

        $this->app['auth']->logout();
        $this->get(route('admin.bookings.show', $booking))
            ->assertRedirect(route('login'));
    }

    public function test_approval_is_rechecked_against_maintenance_and_overlapping_approved_bookings(): void
    {
        $manager = $this->userWithRole('manager');
        $booking = $this->booking();
        $conflict = $this->booking([
            'reference' => 'FG-OVERLAP1',
            'status' => Booking::STATUS_APPROVED,
        ]);

        $this->actingAs($manager)
            ->patchJson(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $conflict->update(['status' => Booking::STATUS_REJECTED]);
        VehicleUnavailability::query()->create([
            'vehicle_id' => 'toyota-vios',
            'start_datetime' => '2026-12-02 09:00:00',
            'end_datetime' => '2026-12-04 09:00:00',
            'reason' => 'Scheduled maintenance',
        ]);

        $this->actingAs($manager)
            ->patchJson(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function assertAuditTransition(
        Booking $booking,
        string $action,
        string $from,
        string $to,
        User $actor,
    ): void {
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
        ]);

        $log = $booking->auditLogs()->where('action', $action)->latest('id')->firstOrFail();
        $this->assertSame($from, $log->changes['from']);
        $this->assertSame($to, $log->changes['to']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function booking(array $overrides = []): Booking
    {
        $customer = Customer::query()->create([
            'full_name' => $overrides['customer_name'] ?? 'Booking Customer',
            'email' => $overrides['email'] ?? 'booking-'.str()->random(12).'@example.com',
            'mobile_number' => $overrides['phone'] ?? '09171234567',
        ]);

        return Booking::query()->create([
            'booking_reference' => $overrides['reference'] ?? 'FG-'.str()->upper(str()->random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => 'toyota-vios',
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => $overrides['pickup'] ?? '2026-12-01 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-12-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 5000,
            'status' => $overrides['status'] ?? Booking::STATUS_PENDING,
        ]);
    }
}
