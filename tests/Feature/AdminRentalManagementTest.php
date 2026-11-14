<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use App\Services\BookingStatusTransitionService;
use App\Services\CustomerManagementService;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AdminRentalManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'Asia/Manila'));
        $this->vehicle();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rental_access_metrics_filters_search_sort_and_role_based_customer_details(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('customer');
        $this->vehicle('toyota-corolla');
        $this->vehicle('toyota-yaris');
        $this->vehicle('toyota-prius');
        $this->vehicle('toyota-camry');
        $this->vehicle('hyundai-elantra');

        $ready = $this->booking([
            'reference' => 'FG-READY01',
            'customer_name' => 'Ready Person',
            'email' => 'ready.person@example.test',
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-01 09:00:00',
            'vehicle_id' => 'toyota-vios',
        ]);
        $this->booking([
            'reference' => 'FG-READY02',
            'customer_name' => 'Another Ready',
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-01 08:00:00',
            'vehicle_id' => 'toyota-corolla',
        ]);
        $this->booking([
            'reference' => 'FG-SCHEDULED',
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-02 09:00:00',
            'vehicle_id' => 'toyota-yaris',
        ]);
        $this->booking([
            'reference' => 'FG-DUETODAY',
            'status' => Booking::STATUS_ONGOING,
            'pickup' => '2026-11-30 09:00:00',
            'returned' => '2026-12-01 18:00:00',
            'vehicle_id' => 'toyota-prius',
        ]);
        $this->booking([
            'reference' => 'FG-OVERDUE',
            'status' => Booking::STATUS_ONGOING,
            'pickup' => '2026-11-30 09:00:00',
            'returned' => '2026-12-01 09:00:00',
            'vehicle_id' => 'toyota-camry',
        ]);
        $completed = $this->booking([
            'reference' => 'FG-COMPLETE',
            'status' => Booking::STATUS_COMPLETED,
        ]);
        $completed->auditLogs()->create([
            'action' => 'rental_checked_in',
            'changes' => ['from' => Booking::STATUS_ONGOING, 'to' => Booking::STATUS_COMPLETED],
            'created_at' => now(),
        ]);
        $this->booking(['reference' => 'FG-PENDING', 'status' => Booking::STATUS_PENDING]);

        $response = $this->actingAs($manager)->get(route('admin.rentals.index'));
        $response->assertOk()
            ->assertSee('Ready for Check-Out')
            ->assertSee('Active Rentals')
            ->assertSee('Due Today')
            ->assertSee('Overdue Returns')
            ->assertSee('Completed This Month')
            ->assertSee('Total Rentals Handled')
            ->assertSee('Showing 1–6 of 6')
            ->assertDontSee('FG-PENDING');

        $this->assertSame(2, (int) $response->viewData('metrics')['ready']);
        $this->assertSame(2, (int) $response->viewData('metrics')['active']);
        $this->assertSame(1, (int) $response->viewData('metrics')['due_today']);
        $this->assertSame(1, (int) $response->viewData('metrics')['overdue']);
        $this->assertSame(1, (int) $response->viewData('metrics')['completed_month']);
        $this->assertSame(6, (int) $response->viewData('metrics')['handled']);

        $this->actingAs($manager)
            ->get(route('admin.rentals.index', ['search' => 'Ready Person', 'status' => 'ready']))
            ->assertOk()
            ->assertSee('FG-READY01')
            ->assertDontSee('FG-READY02')
            ->assertSee('Showing 1–1 of 1')
            ->assertSee('status=ready', false);

        $this->actingAs($manager)
            ->get(route('admin.rentals.index', ['status' => 'ready', 'sort' => 'booking_reference', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['FG-READY01', 'FG-READY02']);

        $this->actingAs($manager)
            ->get(route('admin.rentals.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertSee('FG-OVERDUE')
            ->assertDontSee('FG-DUETODAY');

        $this->actingAs($manager)
            ->get(route('admin.rentals.index', ['status' => 'ready', 'sort' => 'customer', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Another Ready', 'Ready Person']);

        $this->actingAs($manager)
            ->get(route('admin.rentals.show', $ready))
            ->assertOk()
            ->assertSee('r***@example.test')
            ->assertDontSee('ready.person@example.test')
            ->assertDontSee('Check out vehicle');

        $this->actingAs($admin)
            ->get(route('admin.rentals.show', $ready))
            ->assertOk()
            ->assertSee('ready.person@example.test')
            ->assertSee('Check out vehicle');

        $ongoing = $this->booking([
            'reference' => 'FG-MANAGER',
            'status' => Booking::STATUS_ONGOING,
            'vehicle_id' => 'hyundai-elantra',
        ]);
        $this->actingAs($manager)
            ->get(route('admin.rentals.show', $ongoing))
            ->assertOk()
            ->assertDontSee('Check in vehicle');
        $this->postJson(route('admin.rentals.checkin', $ongoing))->assertForbidden();

        $this->actingAs($staff)->get(route('admin.rentals.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.rentals.show', $ready))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.rentals.index'))->assertForbidden();
        $this->app['auth']->logout();
        $this->get(route('admin.rentals.index'))->assertRedirect(route('login'));
    }

    public function test_rental_directory_paginates_and_searches_vehicle_and_reference(): void
    {
        $manager = $this->userWithRole('manager');

        for ($index = 1; $index <= 11; $index++) {
            $this->booking([
                'reference' => sprintf('FG-PAGE%02d', $index),
                'status' => Booking::STATUS_COMPLETED,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('admin.rentals.index', [
            'search' => 'FG-PAGE',
            'sort' => 'booking_reference',
            'direction' => 'asc',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('FG-PAGE11')
            ->assertDontSee('FG-PAGE01')
            ->assertSee('Showing 11–11 of 11')
            ->assertSee('search=FG-PAGE', false);

        $this->get(route('admin.rentals.index', ['search' => 'Fleet Vehicle']))
            ->assertOk()
            ->assertSee('Showing 1–10 of 11');
    }

    public function test_admin_checks_out_a_ready_booking_with_audit_and_preserved_snapshots(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $booking = $this->booking([
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-01 09:00:00',
            'returned' => '2026-12-03 09:00:00',
        ]);
        $original = $this->bookingSnapshot($booking);

        $this->actingAs($manager)
            ->postJson(route('admin.rentals.checkout', $booking))
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.rentals.show', $booking))
            ->post(route('admin.rentals.checkout', $booking), [
                'checked_out_at' => '2000-01-01 00:00:00',
                'checked_in_at' => '2000-01-02 00:00:00',
            ])
            ->assertRedirect(route('admin.rentals.show', $booking));

        $this->assertSame(Booking::STATUS_ONGOING, $booking->fresh()->status);
        $this->assertSame('2026-12-01 10:00:00', $booking->fresh()->checked_out_at->toDateTimeString());
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertSame($original, $this->bookingSnapshot($booking->fresh()));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'rental_checked_out',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
        ]);
        $log = $booking->auditLogs()->where('action', 'rental_checked_out')->firstOrFail();
        $this->assertSame('approved', $log->changes['from']);
        $this->assertSame('ongoing', $log->changes['to']);
        $this->assertSame('toyota-vios', $log->changes['vehicle_id']);
        $this->assertArrayHasKey('checked_out_at', $log->changes);
        $this->assertSame(1, app(CustomerManagementService::class)->metrics(now('Asia/Manila'))['ongoing']);
        $this->assertSame(1, app(VehicleAvailabilityService::class)->fleetStatusAt(now('Asia/Manila'))['ongoing']);
        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(1, $dashboard->viewData('metrics')['active_rentals']);

        $this->postJson(route('admin.rentals.checkout', $booking))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame(1, $booking->auditLogs()->where('action', 'rental_checked_out')->count());
    }

    public function test_checkin_completes_early_and_releases_the_vehicle_without_changing_booking_values(): void
    {
        $admin = $this->userWithRole('admin');
        $booking = $this->booking([
            'status' => Booking::STATUS_ONGOING,
            'pickup' => '2026-12-01 08:00:00',
            'returned' => '2026-12-03 09:00:00',
        ]);
        $original = $this->bookingSnapshot($booking);
        $customer = $booking->customer->only(['full_name', 'email', 'mobile_number']);

        $this->actingAs($admin)
            ->post(route('admin.rentals.checkin', $booking))
            ->assertRedirect(route('admin.rentals.show', $booking));

        $this->assertSame(Booking::STATUS_COMPLETED, $booking->fresh()->status);
        $this->assertSame('2026-12-01 10:00:00', $booking->fresh()->checked_in_at->toDateTimeString());
        $this->assertSame($original, $this->bookingSnapshot($booking->fresh()));
        $this->assertSame($customer, $booking->customer->fresh()->only(array_keys($customer)));
        $log = $booking->auditLogs()->where('action', 'rental_checked_in')->firstOrFail();
        $this->assertSame('ongoing', $log->changes['from']);
        $this->assertSame('completed', $log->changes['to']);
        $this->assertFalse($log->changes['overdue']);
        $this->assertArrayHasKey('checked_in_at', $log->changes);
        $booking->fill(['checked_in_at' => '2000-01-01 00:00:00'])->save();
        $this->assertSame('2026-12-01 10:00:00', $booking->fresh()->checked_in_at->toDateTimeString());

        $this->assertTrue(app(VehicleAvailabilityService::class)->isAvailable(
            $booking->vehicle,
            Carbon::parse('2026-12-01 10:00:00', 'Asia/Manila'),
            Carbon::parse('2026-12-03 09:00:00', 'Asia/Manila'),
        ));

        $metrics = app(CustomerManagementService::class)->metrics(now('Asia/Manila'));
        $this->assertSame(1, $metrics['completed']);
        $this->assertSame(0, $metrics['ongoing']);
        $this->assertSame(1, app(VehicleAvailabilityService::class)->fleetStatusAt(now('Asia/Manila'))['available']);
        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(0, $dashboard->viewData('metrics')['active_rentals']);
    }

    public function test_late_checkin_records_overdue_without_changing_the_scheduled_period(): void
    {
        $admin = $this->userWithRole('admin');
        $booking = $this->booking([
            'status' => Booking::STATUS_ONGOING,
            'pickup' => '2026-11-30 09:00:00',
            'returned' => '2026-12-01 09:00:00',
        ]);
        $originalPickup = $booking->pickup_datetime->toDateTimeString();
        $originalReturn = $booking->return_datetime->toDateTimeString();

        $this->actingAs($admin)
            ->post(route('admin.rentals.checkin', $booking))
            ->assertRedirect(route('admin.rentals.show', $booking));

        $booking->refresh();
        $this->assertSame(Booking::STATUS_COMPLETED, $booking->status);
        $this->assertSame($originalPickup, $booking->pickup_datetime->toDateTimeString());
        $this->assertSame($originalReturn, $booking->return_datetime->toDateTimeString());
        $this->assertTrue($booking->auditLogs()->where('action', 'rental_checked_in')->firstOrFail()->changes['overdue']);
        $this->assertSame('2026-12-01 10:00:00', $booking->checked_in_at->toDateTimeString());
    }

    public function test_rental_timestamps_and_status_changes_roll_back_together(): void
    {
        $admin = $this->userWithRole('admin');
        $approved = $this->booking([
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-01 09:00:00',
        ]);
        $transitions = app(BookingStatusTransitionService::class);

        try {
            DB::transaction(function () use ($approved, $admin, $transitions): void {
                $transitions->transition($approved, Booking::STATUS_ONGOING, $admin);
                throw new RuntimeException('Rollback checkout test transaction.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback checkout test transaction.', $exception->getMessage());
        }

        $approved->refresh();
        $this->assertSame(Booking::STATUS_APPROVED, $approved->status);
        $this->assertNull($approved->checked_out_at);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'rental_checked_out',
            'subject_id' => $approved->id,
        ]);

        $ongoing = $this->booking([
            'status' => Booking::STATUS_ONGOING,
            'reference' => 'FG-ROLLBACK-CHECKIN',
        ]);
        try {
            DB::transaction(function () use ($ongoing, $admin, $transitions): void {
                $transitions->transition($ongoing, Booking::STATUS_COMPLETED, $admin);
                throw new RuntimeException('Rollback check-in test transaction.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback check-in test transaction.', $exception->getMessage());
        }

        $ongoing->refresh();
        $this->assertSame(Booking::STATUS_ONGOING, $ongoing->status);
        $this->assertNull($ongoing->checked_in_at);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'rental_checked_in',
            'subject_id' => $ongoing->id,
        ]);
    }

    public function test_rental_details_and_directory_prefer_columns_and_keep_audit_fallback(): void
    {
        $admin = $this->userWithRole('admin');
        $booking = $this->booking([
            'status' => Booking::STATUS_COMPLETED,
            'pickup' => '2026-11-30 09:00:00',
            'returned' => '2026-12-01 09:00:00',
        ]);
        $booking->forceFill([
            'checked_out_at' => '2026-11-30 09:30:00',
            'checked_in_at' => '2026-12-01 11:00:00',
        ])->save();
        $booking->auditLogs()->createMany([
            [
                'action' => 'rental_checked_out',
                'changes' => [],
                'created_at' => Carbon::parse('2026-11-30 08:00:00', 'Asia/Manila'),
            ],
            [
                'action' => 'rental_checked_in',
                'changes' => ['overdue' => false],
                'created_at' => Carbon::parse('2026-12-01 08:00:00', 'Asia/Manila'),
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rentals.show', $booking))
            ->assertOk()
            ->assertSee('Returned 2 hours late')
            ->assertViewHas('checked_out_at', fn ($time) => $time->toDateTimeString() === '2026-11-30 09:30:00')
            ->assertViewHas('checked_in_at', fn ($time) => $time->toDateTimeString() === '2026-12-01 11:00:00');

        $directory = app(\App\Services\RentalManagementService::class)->directory([], now('Asia/Manila'));
        $listed = $directory->getCollection()->firstWhere('id', $booking->id);
        $this->assertSame('2026-11-30 09:30:00', $listed->effective_checked_out_at->toDateTimeString());
        $this->assertSame('2026-12-01 11:00:00', $listed->effective_checked_in_at->toDateTimeString());

        $fallback = $this->booking([
            'status' => Booking::STATUS_COMPLETED,
            'reference' => 'FG-AUDIT-FALLBACK',
            'returned' => '2026-12-01 11:00:00',
        ]);
        $fallback->auditLogs()->createMany([
            [
                'action' => 'booking_marked_ongoing',
                'changes' => [],
                'created_at' => Carbon::parse('2026-12-01 08:30:00', 'Asia/Manila'),
            ],
            [
                'action' => 'booking_marked_completed',
                'changes' => [],
                'created_at' => Carbon::parse('2026-12-01 12:00:00', 'Asia/Manila'),
            ],
        ]);
        $fallbackDetails = app(\App\Services\RentalManagementService::class)->details($fallback, now('Asia/Manila'), true);
        $this->assertSame('2026-12-01 08:30:00', $fallbackDetails['checked_out_at']->toDateTimeString());
        $this->assertSame('2026-12-01 12:00:00', $fallbackDetails['checked_in_at']->toDateTimeString());
        $this->assertTrue($fallbackDetails['late_return']);
        $this->assertSame('Returned 1 hour late', $fallbackDetails['return_comparison']);
        $this->assertTrue($fallbackDetails['timeline']->firstWhere('label', 'Rental marked completed')['changes']['overdue']);
        $this->actingAs($admin)
            ->get(route('admin.rentals.show', $fallback))
            ->assertOk()
            ->assertSee('Returned late')
            ->assertSee('Returned 1 hour late');
        $fallbackListed = app(\App\Services\RentalManagementService::class)
            ->directory([], now('Asia/Manila'))
            ->getCollection()
            ->firstWhere('id', $fallback->id);
        $this->assertTrue($fallbackListed->late_return);
    }

    public function test_check_out_blocks_invalid_statuses_future_pickup_and_direct_reservation_transitions(): void
    {
        $admin = $this->userWithRole('admin');

        foreach ([
            Booking::STATUS_PENDING,
            Booking::STATUS_REJECTED,
            Booking::STATUS_ONGOING,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CANCELLED,
        ] as $status) {
            $booking = $this->booking(['status' => $status]);
            $this->actingAs($admin)
                ->postJson(route('admin.rentals.checkout', $booking))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $future = $this->booking([
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-01 11:00:00',
        ]);
        $this->postJson(route('admin.rentals.checkout', $future))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson(route('admin.bookings.status', $future), ['status' => Booking::STATUS_ONGOING])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_check_out_rejects_inactive_maintenance_and_conflicting_vehicles(): void
    {
        $admin = $this->userWithRole('admin');
        $booking = $this->booking([
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-01 09:00:00',
            'returned' => '2026-12-03 09:00:00',
        ]);

        $booking->vehicle->update(['status' => Vehicle::STATUS_INACTIVE]);
        $this->actingAs($admin)
            ->postJson(route('admin.rentals.checkout', $booking))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $booking->vehicle->update(['status' => Vehicle::STATUS_ACTIVE]);
        VehicleUnavailability::query()->create([
            'vehicle_id' => $booking->vehicle_id,
            'start_datetime' => '2026-12-02 10:00:00',
            'end_datetime' => '2026-12-02 12:00:00',
            'reason' => 'QA maintenance overlap',
        ]);
        $this->postJson(route('admin.rentals.checkout', $booking))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        VehicleUnavailability::query()->delete();
        $this->booking([
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2026-12-02 08:00:00',
            'returned' => '2026-12-02 13:00:00',
        ]);
        $this->postJson(route('admin.rentals.checkout', $booking))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(Booking::STATUS_APPROVED, $booking->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_ongoing_rentals_can_be_checked_in_and_active_rentals_cannot_be_cancelled(): void
    {
        $admin = $this->userWithRole('admin');
        $approved = $this->booking(['status' => Booking::STATUS_APPROVED]);

        $this->actingAs($admin)
            ->postJson(route('admin.rentals.checkin', $approved))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $ongoing = $this->booking([
            'status' => Booking::STATUS_ONGOING,
            'reference' => 'FG-NOCANCEL',
        ]);
        $this->patchJson(route('admin.bookings.status', $ongoing), ['status' => Booking::STATUS_CANCELLED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame(Booking::STATUS_ONGOING, $ongoing->fresh()->status);
    }

    private function vehicle(string $id = 'toyota-vios'): Vehicle
    {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => 'Fleet Vehicle',
            'brand' => 'Toyota',
            'model' => 'Vios',
            'category' => 'sedan',
            'description' => 'Test vehicle.',
            'daily_rate' => 2500,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
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
            'full_name' => $overrides['customer_name'] ?? 'Rental Customer',
            'email' => $overrides['email'] ?? 'rental-'.str()->random(12).'@example.test',
            'mobile_number' => $overrides['phone'] ?? '09171234567',
        ]);

        return Booking::query()->create([
            'booking_reference' => $overrides['reference'] ?? 'FG-'.str()->upper(str()->random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => $overrides['vehicle_id'] ?? 'toyota-vios',
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => $overrides['pickup'] ?? '2026-12-01 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => $overrides['returned'] ?? '2026-12-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 5000,
            'status' => $overrides['status'] ?? Booking::STATUS_PENDING,
        ]);
    }

    private function bookingSnapshot(Booking $booking): array
    {
        return [
            'customer_id' => $booking->customer_id,
            'vehicle_id' => $booking->vehicle_id,
            'pickup_datetime' => $booking->pickup_datetime->toDateTimeString(),
            'return_datetime' => $booking->return_datetime->toDateTimeString(),
            'rental_duration_days' => $booking->rental_duration_days,
            'daily_rate_snapshot' => $booking->daily_rate_snapshot,
            'estimated_total' => $booking->estimated_total,
        ];
    }
}
