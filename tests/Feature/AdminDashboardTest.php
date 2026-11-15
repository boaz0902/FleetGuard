<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-14 08:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authorized_dashboard_uses_live_metrics_activity_and_recent_reservations(): void
    {
        $admin = $this->userWithRole('admin');
        $available = $this->vehicle('available-car');
        $reserved = $this->vehicle('reserved-car');
        $ongoing = $this->vehicle('ongoing-car');
        $maintenance = $this->vehicle('maintenance-car', Vehicle::STATUS_MAINTENANCE);
        $inactive = $this->vehicle('inactive-car', Vehicle::STATUS_INACTIVE);

        $this->createBooking($available, Booking::STATUS_PENDING, 'Pending customer', 'pending@example.com');
        $this->createBooking($reserved, Booking::STATUS_APPROVED, 'Reserved customer', 'reserved@example.com', [
            'pickup_datetime' => '2026-11-14 07:00:00',
            'return_datetime' => '2026-11-14 10:00:00',
        ]);
        $this->createBooking($ongoing, Booking::STATUS_ONGOING, 'Rental customer', 'rental@example.com', [
            'pickup_datetime' => '2026-11-14 07:00:00',
            'return_datetime' => '2026-11-14 10:00:00',
        ]);
        $this->createBooking($inactive, Booking::STATUS_COMPLETED, 'Older customer', 'older@example.com', [
            'created_at' => Carbon::now('Asia/Manila')->subDays(8),
        ]);
        VehicleUnavailability::query()->create([
            'vehicle_id' => $maintenance->id,
            'start_datetime' => '2026-11-14 07:00:00',
            'end_datetime' => '2026-11-14 10:00:00',
            'reason' => 'Routine service',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                return $metrics === [
                    'total_vehicles' => 5,
                    'available_vehicles' => 1,
                    'pending_reservations' => 1,
                    'active_rentals' => 1,
                    'total_reservations' => 4,
                ];
            })
            ->assertViewHas('fleetStatus', [
                'available' => 1,
                'reserved' => 1,
                'ongoing' => 1,
                'maintenance' => 1,
                'inactive' => 1,
            ])
            ->assertSee('Pending customer')
            ->assertSee('FG-')
            ->assertDontSee('pending@example.com')
            ->assertSee('Reservations over the last 7 days');

        $this->actingAs($admin)
            ->get(route('admin.calendar.index'))
            ->assertOk()
            ->assertViewHas('fleetStatus', [
                'total' => 5,
                'available' => 1,
                'reserved' => 1,
                'ongoing' => 1,
                'maintenance' => 1,
                'inactive' => 1,
            ])
            ->assertSee('Fleet Availability')
            ->assertSee('Vehicle timeline');
    }

    public function test_dashboard_chart_and_recent_reservations_have_empty_states(): void
    {
        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('No reservations were created in the last 7 days.')
            ->assertSee('No reservations yet')
            ->assertViewHas('metrics', [
                'total_vehicles' => 0,
                'available_vehicles' => 0,
                'pending_reservations' => 0,
                'active_rentals' => 0,
                'total_reservations' => 0,
            ]);
    }

    public function test_dashboard_requires_authorized_admin_or_manager_access(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->userWithRole('customer'))->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.dashboard'))->assertOk();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function vehicle(string $id, string $status = Vehicle::STATUS_ACTIVE): Vehicle
    {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => ucfirst(str_replace('-', ' ', $id)),
            'brand' => 'FleetGuard',
            'model' => 'Test',
            'category' => 'sedan',
            'description' => 'Dashboard test vehicle.',
            'daily_rate' => 2500,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => null,
            'status' => $status,
        ]);
    }

    private function createBooking(
        Vehicle $vehicle,
        string $status,
        string $customerName,
        string $email,
        array $overrides = [],
    ): Booking {
        $customer = Customer::query()->create([
            'full_name' => $customerName,
            'email' => $email,
            'mobile_number' => '09171234567',
        ]);

        return Booking::query()->create(array_replace([
            'booking_reference' => 'FG-'.strtoupper(str()->random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-12-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 5000,
            'status' => $status,
        ], $overrides));
    }
}
