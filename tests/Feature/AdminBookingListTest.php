<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_list_shows_database_counts_and_filters_reservations(): void
    {
        $manager = $this->userWithRole('manager');
        $vios = $this->vehicle('toyota-vios', 'Toyota Vios');
        $fortuner = $this->vehicle('toyota-fortuner', 'Toyota Fortuner');
        $matchingBooking = $this->booking(
            $vios,
            'FG-MATCH123',
            'Alice Example',
            'alice@example.com',
            Booking::STATUS_PENDING,
            '2026-12-01 09:00:00',
        );
        $this->booking(
            $fortuner,
            'FG-OTHER456',
            'Benny Example',
            'benny@example.com',
            Booking::STATUS_APPROVED,
            '2026-12-10 09:00:00',
            '2026-12-12 09:00:00',
        );

        $this->actingAs($manager)
            ->get(route('admin.bookings.index', [
                'search' => 'Alice',
                'status' => Booking::STATUS_PENDING,
                'vehicle_id' => $vios->id,
                'pickup_from' => '2026-12-01',
                'pickup_to' => '2026-12-05',
            ]))
            ->assertOk()
            ->assertSee('method="GET"', false)
            ->assertSee('data-preserve-search-position', false)
            ->assertSee('Press Enter to search')
            ->assertDontSee('Apply filters')
            ->assertSee($matchingBooking->booking_reference)
            ->assertSee('Alice Example')
            ->assertDontSee('FG-OTHER456')
            ->assertDontSee('alice@example.com')
            ->assertSee(route('admin.bookings.show', $matchingBooking), false)
            ->assertViewHas('metrics', [
                'total' => 2,
                'pending' => 1,
                'approved' => 1,
                'ongoing' => 0,
                'completed' => 0,
            ]);

        $this->actingAs($manager)
            ->get(route('admin.bookings.index', ['search' => 'FG-OTHER456']))
            ->assertOk()
            ->assertSee('FG-OTHER456')
            ->assertDontSee('FG-MATCH123');
    }

    public function test_booking_search_covers_visible_fields_without_filter_controls(): void
    {
        $manager = $this->userWithRole('manager');
        $vehicle = $this->vehicle('toyota-vios', 'Toyota Vios');
        $booking = $this->booking(
            $vehicle,
            'FG-SEARCH789',
            'Alice Example',
            'alice@example.com',
            Booking::STATUS_PENDING,
            '2026-11-15 09:00:00',
            '2026-11-16 16:00:00',
        );
        $booking->forceFill(['created_at' => '2026-11-14 08:00:00'])->save();

        foreach ([
            'FG-SEARCH789',
            'Alice Example',
            'Toyota Vios',
            '2026-11-15',
            '2026-11-16',
            'Nov 15',
            'Nov 16',
            'Nov 14',
            'Nov',
            'November',
            'nOvEmBeR',
            '2 days',
            '₱5,000.00',
            'Pending',
            'Nov 14, 2026',
            '9:00',
            '9:00 AM',
            '4:00',
            '4:00 PM',
            '8:00 AM',
        ] as $search) {
            $this->actingAs($manager)
                ->get(route('admin.bookings.index', ['search' => $search]))
                ->assertOk()
                ->assertSee($booking->booking_reference)
                ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 1)
                ->assertSee('Press Enter to search')
                ->assertDontSee('name="status"', false)
                ->assertDontSee('name="vehicle_id"', false)
                ->assertDontSee('name="pickup_from"', false)
                ->assertDontSee('name="pickup_to"', false)
                ->assertDontSee('Clear');
        }
    }

    public function test_booking_details_use_shared_admin_section_surfaces(): void
    {
        $manager = $this->userWithRole('manager');
        $vehicle = $this->vehicle('toyota-vios', 'Toyota Vios');
        $booking = $this->booking(
            $vehicle,
            'FG-DETAIL123',
            'Alice Example',
            'alice@example.com',
            Booking::STATUS_PENDING,
            '2026-12-01 09:00:00',
        );

        $this->actingAs($manager)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('admin-section-card', false);
    }

    public function test_booking_list_rejects_unknown_status_and_reversed_pickup_dates(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.bookings.index', ['status' => 'unknown']))
            ->assertSessionHasErrors('status');

        $this->get(route('admin.bookings.index', [
            'pickup_from' => '2026-12-05',
            'pickup_to' => '2026-12-01',
        ]))->assertSessionHasErrors('pickup_to');
    }

    public function test_admin_sidebar_connects_reservations_calendar_and_dashboard(): void
    {
        $manager = $this->userWithRole('manager');
        $navigationRoutes = [
            route('admin.dashboard'),
            route('admin.bookings.index'),
            route('admin.calendar.index'),
        ];

        $reservationsResponse = $this->actingAs($manager)
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('aria-label="Admin navigation"', false)
            ->assertSee('aria-current="page"', false);

        $calendarResponse = $this->get(route('admin.calendar.index'))
            ->assertOk()
            ->assertSee('aria-label="Admin navigation"', false)
            ->assertSee('aria-current="page"', false);

        foreach ($navigationRoutes as $route) {
            $reservationsResponse->assertSee($route, false);
            $calendarResponse->assertSee($route, false);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function vehicle(string $id, string $name): Vehicle
    {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => $name,
            'brand' => 'Toyota',
            'model' => $name,
            'category' => 'sedan',
            'description' => 'Test vehicle.',
            'daily_rate' => 2500,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => null,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    private function booking(
        Vehicle $vehicle,
        string $reference,
        string $customerName,
        string $email,
        string $status,
        string $pickup,
        string $returned = '2026-12-03 09:00:00',
    ): Booking {
        $customer = Customer::query()->create([
            'full_name' => $customerName,
            'email' => $email,
            'mobile_number' => '09171234567',
        ]);

        return Booking::query()->create([
            'booking_reference' => $reference,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => $pickup,
            'return_location' => 'Panabo City',
            'return_datetime' => $returned,
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 5000,
            'status' => $status,
        ]);
    }
}
