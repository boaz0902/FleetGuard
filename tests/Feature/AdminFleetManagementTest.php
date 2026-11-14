<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFleetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fleet_access_matches_roles_and_customers_cannot_open_inventory(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('customer');
        $vehicle = $this->vehicle();

        $this->actingAs($admin)->get(route('admin.fleet.index'))->assertOk();
        $this->actingAs($manager)
            ->get(route('admin.fleet.index'))
            ->assertOk()
            ->assertDontSee('Add vehicle')
            ->assertDontSee('Edit '.$vehicle->name);
        $this->actingAs($staff)->get(route('admin.fleet.index'))->assertOk();
        $this->actingAs($customer)->get(route('admin.fleet.index'))->assertForbidden();
        $this->app['auth']->logout();
        $this->get(route('admin.fleet.index'))->assertRedirect(route('login'));

        $this->actingAs($manager)
            ->get(route('admin.fleet.create'))
            ->assertForbidden();
        $this->actingAs($staff)
            ->get(route('admin.fleet.edit', $vehicle))
            ->assertForbidden();
    }

    public function test_fleet_search_pagination_and_maintenance_vehicle_selector_are_independent(): void
    {
        $admin = $this->userWithRole('admin');
        $this->vehicle('toyota-vios', ['name' => 'Toyota Vios', 'status' => Vehicle::STATUS_ACTIVE]);

        for ($index = 1; $index <= 26; $index++) {
            $this->vehicle('fleet-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), [
                'name' => 'Searchable Vehicle '.$index,
                'status' => Vehicle::STATUS_INACTIVE,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.fleet.index', [
                'search' => 'Searchable',
                'status' => Vehicle::STATUS_INACTIVE,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('method="GET"', false)
            ->assertSee('data-preserve-search-position', false)
            ->assertSee('Press Enter to search')
            ->assertDontSee('Apply filters')
            ->assertSee('name="category"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="sort"', false)
            ->assertSee('name="direction"', false)
            ->assertSee('data-fleet-view-toggle="grid"', false)
            ->assertSee('admin-fleet-category-pill', false)
            ->assertSee('Fleet health')
            ->assertSee('Searchable Vehicle 26')
            ->assertSee('Toyota Vios')
            ->assertSee('of 26 vehicles')
            ->assertSee(route('admin.fleet.index', ['search' => 'available']), false)
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->currentPage() === 2 && $vehicles->total() === 26)
            ->assertSee('search=Searchable', false);
    }

    public function test_fleet_search_covers_visible_fields_and_keeps_toolbar_filters_available(): void
    {
        $admin = $this->userWithRole('admin');
        Carbon::setTestNow(Carbon::parse('2031-02-01 10:00:00', 'Asia/Manila'));
        $vehicle = $this->vehicle('searchable-vehicle', [
            'name' => 'Fleet Demo',
            'brand' => 'FleetGuard',
            'model' => 'City',
            'category' => 'sedan',
            'daily_rate' => '2800.00',
        ]);
        $vehicle->forceFill(['created_at' => '2031-02-01 08:00:00'])->save();
        $this->booking($vehicle, [
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2031-02-01 09:00:00',
            'returned' => '2031-02-01 17:00:00',
        ]);

        foreach ([
            'Fleet Demo',
            'FleetGuard',
            'City',
            'sedan',
            'active',
            'reserved',
            '₱2,800.00',
            'Feb 01, 2031',
            'Feb 01',
            'Feb 1',
            'Feb',
            'February',
            'fEbRuArY',
        ] as $search) {
            $this->actingAs($admin)
                ->get(route('admin.fleet.index', ['search' => $search]))
                ->assertOk()
                ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->total() === 1 && $vehicles->first()->is($vehicle))
                ->assertSee('Press Enter to search')
                ->assertSee('name="category"', false)
                ->assertSee('name="status"', false)
                ->assertSee('name="sort"', false)
                ->assertSee('name="direction"', false)
                ->assertSee('Clear filters');
        }
    }

    public function test_fleet_filter_chips_can_be_removed_without_dropping_other_filters(): void
    {
        $admin = $this->userWithRole('admin');
        $this->vehicle('inactive-suv', [
            'name' => 'Inactive SUV',
            'category' => 'suv',
            'status' => Vehicle::STATUS_INACTIVE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fleet.index', [
                'category' => 'suv',
                'status' => Vehicle::STATUS_INACTIVE,
                'sort' => 'name',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSee('Category: Suv')
            ->assertSee('Status: Inactive')
            ->assertSee('Sort: Name')
            ->assertSee('Order: Oldest / A–Z')
            ->assertSee('status=inactive&amp;sort=name&amp;direction=asc', false)
            ->assertSee('Inactive SUV');
    }

    public function test_fleet_summary_uses_current_database_availability_rules(): void
    {
        $admin = $this->userWithRole('admin');
        Carbon::setTestNow(Carbon::parse('2031-03-15 09:00:00', 'Asia/Manila'));

        $this->vehicle('available-vehicle');
        $reserved = $this->vehicle('reserved-vehicle');
        $ongoing = $this->vehicle('ongoing-vehicle');
        $inMaintenance = $this->vehicle('maintenance-vehicle');
        $this->vehicle('inactive-vehicle', ['status' => Vehicle::STATUS_INACTIVE]);
        $pendingOnly = $this->vehicle('pending-only-vehicle');

        $this->booking($reserved, [
            'status' => Booking::STATUS_APPROVED,
            'pickup' => '2031-03-15 09:00:00',
            'returned' => '2031-03-15 17:00:00',
        ]);
        $this->booking($ongoing, [
            'status' => Booking::STATUS_ONGOING,
            'pickup' => '2031-03-15 08:00:00',
            'returned' => '2031-03-15 17:00:00',
        ]);
        $this->booking($pendingOnly, [
            'status' => Booking::STATUS_PENDING,
            'pickup' => '2031-03-15 09:00:00',
            'returned' => '2031-03-15 17:00:00',
        ]);
        VehicleUnavailability::query()->create([
            'vehicle_id' => $inMaintenance->id,
            'start_datetime' => '2031-03-15 08:00:00',
            'end_datetime' => '2031-03-15 10:00:00',
            'reason' => 'Service',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fleet.index'))
            ->assertOk()
            ->assertViewHas('fleetStatus', [
                'total' => 6,
                'available' => 2,
                'reserved' => 1,
                'ongoing' => 1,
                'maintenance' => 1,
                'inactive' => 1,
                'active' => 5,
            ]);
    }

    public function test_admin_can_create_and_update_vehicles_with_auditing_and_preserved_booking_snapshots(): void
    {
        $admin = $this->userWithRole('admin');
        $vehicle = $this->vehicle();
        $booking = $this->booking($vehicle, [
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 5000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.fleet.store'), [
                ...$this->vehicleInput(),
                'id' => 'attacker-chosen-id',
                'role' => 'admin',
            ])
            ->assertRedirect();

        $createdVehicle = Vehicle::query()->where('name', 'Fleet Test Car')->firstOrFail();
        $this->assertNotSame('attacker-chosen-id', $createdVehicle->id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle_created',
            'subject_type' => Vehicle::class,
            'subject_id' => $createdVehicle->id,
        ]);

        $this->put(route('admin.fleet.update', $vehicle), [
            ...$this->vehicleInput(),
            'name' => 'Updated Fleet Test Car',
            'daily_rate' => '3100.00',
            'status' => Vehicle::STATUS_ACTIVE,
        ])->assertRedirect(route('admin.fleet.show', $vehicle));

        $this->assertSame('3100.00', $vehicle->fresh()->daily_rate);
        $this->assertSame('2500.00', $booking->fresh()->daily_rate_snapshot);
        $this->assertSame('5000.00', $booking->fresh()->estimated_total);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle_updated',
            'subject_type' => Vehicle::class,
            'subject_id' => $vehicle->id,
        ]);
        $this->assertSame(Vehicle::class, $vehicle->auditLogs()->latest('id')->firstOrFail()->subject_type);
    }

    public function test_invalid_vehicle_fields_and_non_admin_mutations_are_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $vehicle = $this->vehicle();

        $this->actingAs($admin)
            ->from(route('admin.fleet.create'))
            ->post(route('admin.fleet.store'), [
                ...$this->vehicleInput(),
                'daily_rate' => '-1',
                'passenger_capacity' => '0',
                'image_path' => '../private.png',
            ])
            ->assertRedirect(route('admin.fleet.create'))
            ->assertSessionHasErrors(['daily_rate', 'passenger_capacity', 'image_path']);

        $this->actingAs($manager)
            ->post(route('admin.fleet.store'), $this->vehicleInput())
            ->assertForbidden();
        $this->put(route('admin.fleet.update', $vehicle), $this->vehicleInput())
            ->assertForbidden();
    }

    public function test_vehicle_deactivation_is_blocked_by_approved_or_ongoing_bookings(): void
    {
        $admin = $this->userWithRole('admin');
        $vehicle = $this->vehicle();

        foreach ([Booking::STATUS_APPROVED, Booking::STATUS_ONGOING] as $status) {
            $this->booking($vehicle, [
                'status' => $status,
                'pickup' => '2031-02-01 09:00:00',
                'returned' => '2031-02-04 09:00:00',
            ]);

            $this->actingAs($admin)
                ->from(route('admin.fleet.edit', $vehicle))
                ->put(route('admin.fleet.update', $vehicle), [
                    ...$this->vehicleInput(),
                    'status' => Vehicle::STATUS_MAINTENANCE,
                ])
                ->assertRedirect(route('admin.fleet.edit', $vehicle))
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(Vehicle::STATUS_ACTIVE, $vehicle->fresh()->status);
    }

    public function test_maintenance_conflicts_with_approved_or_ongoing_bookings_but_allows_exclusive_end_adjacency(): void
    {
        $admin = $this->userWithRole('admin');
        $vehicle = $this->vehicle();

        foreach ([Booking::STATUS_APPROVED, Booking::STATUS_ONGOING] as $index => $status) {
            $pickup = sprintf('2031-02-%02d 09:00:00', 1 + ($index * 5));
            $returned = sprintf('2031-02-%02d 09:00:00', 4 + ($index * 5));
            $this->booking($vehicle, [
                'status' => $status,
                'pickup' => $pickup,
                'returned' => $returned,
            ]);

            $start = Carbon::parse($pickup, 'Asia/Manila')->addDay()->format('Y-m-d\TH:i');
            $end = Carbon::parse($returned, 'Asia/Manila')->addDay()->format('Y-m-d\TH:i');
            $this->actingAs($admin)
                ->postJson(route('admin.fleet.maintenance.store'), [
                    'vehicle_id' => $vehicle->id,
                    'start_datetime' => $start,
                    'end_datetime' => $end,
                    'reason' => 'Service appointment',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('start_datetime');
        }

        $this->actingAs($admin)
            ->post(route('admin.fleet.maintenance.store'), [
                'vehicle_id' => $vehicle->id,
                'start_datetime' => '2031-02-04T09:00',
                'end_datetime' => '2031-02-05T09:00',
                'reason' => 'Adjacent service appointment',
            ])
            ->assertRedirect(route('admin.fleet.index'));

        $this->assertDatabaseHas('vehicle_unavailabilities', [
            'vehicle_id' => $vehicle->id,
            'start_datetime' => '2031-02-04 09:00:00',
        ]);
    }

    public function test_pending_rejected_and_cancelled_bookings_do_not_block_maintenance_and_removal_is_audited(): void
    {
        $admin = $this->userWithRole('admin');
        $vehicle = $this->vehicle();

        foreach ([
            Booking::STATUS_PENDING,
            Booking::STATUS_REJECTED,
            Booking::STATUS_CANCELLED,
        ] as $index => $status) {
            $day = 1 + ($index * 3);
            $pickup = sprintf('2031-03-%02d 09:00:00', $day);
            $returned = sprintf('2031-03-%02d 09:00:00', $day + 2);
            $this->booking($vehicle, [
                'status' => $status,
                'pickup' => $pickup,
                'returned' => $returned,
            ]);

            $this->actingAs($admin)
                ->post(route('admin.fleet.maintenance.store'), [
                    'vehicle_id' => $vehicle->id,
                    'start_datetime' => Carbon::parse($pickup, 'Asia/Manila')->addHour()->format('Y-m-d\TH:i'),
                    'end_datetime' => Carbon::parse($returned, 'Asia/Manila')->subHour()->format('Y-m-d\TH:i'),
                    'reason' => 'Routine inspection',
                ])
                ->assertRedirect(route('admin.fleet.index'));
        }

        $period = VehicleUnavailability::query()->firstOrFail();
        $this->delete(route('admin.fleet.maintenance.destroy', $period))
            ->assertRedirect(route('admin.fleet.index'));

        $this->assertDatabaseMissing('vehicle_unavailabilities', ['id' => $period->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle_unavailability_created',
            'subject_type' => VehicleUnavailability::class,
            'subject_id' => $period->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle_unavailability_removed',
            'subject_type' => VehicleUnavailability::class,
            'subject_id' => $period->id,
        ]);
    }

    public function test_managers_see_booking_schedule_without_customer_contact_data_and_staff_do_not_see_bookings(): void
    {
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $vehicle = $this->vehicle();
        $booking = $this->booking($vehicle, [
            'status' => Booking::STATUS_APPROVED,
            'customer_phone' => '09171112222',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.fleet.show', $vehicle))
            ->assertOk()
            ->assertSee('admin-section-card', false)
            ->assertSee($booking->booking_reference)
            ->assertDontSee('09171112222')
            ->assertSee('Booking history')
            ->assertSee('Total bookings')
            ->assertSee('Upcoming approved')
            ->assertSee('data-fleet-tabs', false);

        $this->actingAs($staff)
            ->get(route('admin.fleet.show', $vehicle))
            ->assertOk()
            ->assertDontSee($booking->booking_reference)
            ->assertDontSee('Booking history')
            ->assertDontSee('id="fleet-tab-bookings"', false);
    }

    public function test_vehicle_detail_shows_maintenance_creator_from_existing_audit_data(): void
    {
        $admin = $this->userWithRole('admin');
        $vehicle = $this->vehicle();
        Carbon::setTestNow(Carbon::parse('2031-02-01 09:00:00', 'Asia/Manila'));
        $period = VehicleUnavailability::query()->create([
            'vehicle_id' => $vehicle->id,
            'start_datetime' => '2031-02-03 09:00:00',
            'end_datetime' => '2031-02-03 17:00:00',
            'reason' => 'Scheduled inspection',
        ]);
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'vehicle_unavailability_created',
            'subject_type' => VehicleUnavailability::class,
            'subject_id' => $period->id,
            'changes' => [
                'vehicle_id' => $vehicle->id,
                'reason' => $period->reason,
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fleet.show', $vehicle))
            ->assertOk()
            ->assertSee('Maintenance timeline')
            ->assertSee('Scheduled inspection')
            ->assertSee('Scheduled')
            ->assertSee('Created by '.$admin->name);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function vehicle(string $id = 'toyota-vios', array $overrides = []): Vehicle
    {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => 'Toyota Vios',
            'brand' => 'Toyota',
            'model' => 'Vios',
            'category' => 'sedan',
            'description' => 'Compact sedan.',
            'daily_rate' => '2500.00',
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
            ...$overrides,
        ]);
    }

    private function vehicleInput(): array
    {
        return [
            'name' => 'Fleet Test Car',
            'brand' => 'Fleet',
            'model' => 'Test',
            'category' => 'sedan',
            'description' => 'Test vehicle.',
            'daily_rate' => '2800.00',
            'passenger_capacity' => 4,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
            'image_path' => '',
        ];
    }

    private function booking(Vehicle $vehicle, array $overrides = []): Booking
    {
        $customer = Customer::query()->create([
            'full_name' => 'Fleet Booking Customer',
            'email' => 'fleet-'.str()->random(12).'@example.com',
            'mobile_number' => $overrides['customer_phone'] ?? '09170000000',
        ]);

        return Booking::query()->create([
            'booking_reference' => 'FG-'.str()->upper(str()->random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => $overrides['pickup'] ?? '2031-02-01 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => $overrides['returned'] ?? '2031-02-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => $overrides['daily_rate_snapshot'] ?? '2500.00',
            'estimated_total' => $overrides['estimated_total'] ?? '5000.00',
            'status' => $overrides['status'] ?? Booking::STATUS_PENDING,
        ]);
    }
}
