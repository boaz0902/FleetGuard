<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaFollowupMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_followup_migrations_are_reversible_and_backfill_only_unambiguous_events(): void
    {
        $customer = Customer::query()->create([
            'full_name' => 'Migration Customer',
            'email' => 'migration@example.test',
            'mobile_number' => '09171234567',
        ]);
        $author = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'id' => 'migration-car',
            'name' => 'Migration Car',
            'brand' => 'Test',
            'model' => 'Car',
            'category' => 'sedan',
            'description' => 'Migration test vehicle.',
            'daily_rate' => 1000,
            'passenger_capacity' => 4,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
        $unambiguous = $this->booking($customer, $vehicle, 'FG-MIGRATION-ONE');
        $ambiguous = $this->booking($customer, $vehicle, 'FG-MIGRATION-TWO');
        $unambiguous->auditLogs()->createMany([
            [
                'action' => 'booking_marked_ongoing',
                'created_at' => Carbon::parse('2026-11-10 09:00:00', 'Asia/Manila'),
                'changes' => [],
            ],
            [
                'action' => 'booking_marked_completed',
                'created_at' => Carbon::parse('2026-11-11 09:00:00', 'Asia/Manila'),
                'changes' => [],
            ],
        ]);
        $ambiguous->auditLogs()->createMany([
            [
                'action' => 'rental_checked_out',
                'created_at' => Carbon::parse('2026-11-10 10:00:00', 'Asia/Manila'),
                'changes' => [],
            ],
            [
                'action' => 'booking_marked_ongoing',
                'created_at' => Carbon::parse('2026-11-10 10:05:00', 'Asia/Manila'),
                'changes' => [],
            ],
        ]);
        CustomerNote::query()->create([
            'customer_id' => $customer->id,
            'author_id' => $author->id,
            'body' => 'Schema rollback fixture.',
        ]);
        $customer->forceFill(['status' => Customer::STATUS_BLOCKED])->save();

        $timestamps = require database_path('migrations/2026_11_14_000008_add_rental_timestamps_to_bookings.php');
        $status = require database_path('migrations/2026_11_14_000009_add_status_to_customers.php');
        $notes = require database_path('migrations/2026_11_14_000010_create_customer_notes_table.php');

        $notes->down();
        $status->down();
        $timestamps->down();

        $this->assertFalse(Schema::hasTable('customer_notes'));
        $this->assertFalse(Schema::hasColumn('customers', 'status'));
        $this->assertFalse(Schema::hasColumn('bookings', 'checked_out_at'));
        $this->assertFalse(Schema::hasColumn('bookings', 'checked_in_at'));
        $this->assertSame(2, Booking::query()->count());
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(2, $customer->bookings()->count());

        $timestamps->up();
        $status->up();
        $notes->up();

        $this->assertTrue(Schema::hasTable('customer_notes'));
        $this->assertTrue(Schema::hasColumn('customers', 'status'));
        $this->assertTrue(Schema::hasColumn('bookings', 'checked_out_at'));
        $this->assertTrue(Schema::hasColumn('bookings', 'checked_in_at'));
        $this->assertSame(Customer::STATUS_ACTIVE, $customer->fresh()->status);
        $this->assertSame('2026-11-10 09:00:00', $unambiguous->fresh()->checked_out_at->toDateTimeString());
        $this->assertSame('2026-11-11 09:00:00', $unambiguous->fresh()->checked_in_at->toDateTimeString());
        $this->assertNull($ambiguous->fresh()->checked_out_at);
        $this->assertSame(2, Booking::query()->count());
        $this->assertSame(1, Customer::query()->count());
    }

    private function booking(Customer $customer, Vehicle $vehicle, string $reference): Booking
    {
        return Booking::query()->create([
            'booking_reference' => $reference,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-11-10 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-11-11 09:00:00',
            'rental_duration_days' => 1,
            'daily_rate_snapshot' => 1000,
            'estimated_total' => 1000,
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }
}
