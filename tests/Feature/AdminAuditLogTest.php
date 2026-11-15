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

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_audit_log_access_matches_the_policy_and_hides_the_link_from_other_roles(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('customer');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Logs');

        $this->actingAs($manager)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Logs');

        $this->actingAs($staff)
            ->get(route('admin.fleet.index'))
            ->assertOk()
            ->assertDontSee(route('admin.audit-logs.index'));
        $this->get(route('admin.audit-logs.index'))->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();

        $this->app['auth']->logout();

        $this->get(route('admin.audit-logs.index'))->assertRedirect(route('admin.login'));
    }

    public function test_directory_aggregates_metrics_and_paginates_newest_first(): void
    {
        $admin = $this->userWithRole('admin', 'Ada Administrator');
        $manager = $this->userWithRole('manager', 'Morgan Manager');

        for ($index = 0; $index < 27; $index++) {
            $actor = $index % 2 === 0 ? $admin : $manager;
            $this->audit(
                $actor,
                'audit_test_'.$index,
                ['sequence' => $index],
                User::class,
                (string) $actor->id,
                now()->subMinutes(26 - $index),
            );
        }

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'));

        $response->assertOk()
            ->assertSee('Showing 1–25 of 27')
            ->assertSee('Total entries')
            ->assertSee('Last 7 days')
            ->assertSeeInOrder(['Audit Test 26', 'Audit Test 25', 'Audit Test 24'])
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics === [
                'total' => 27,
                'today' => 27,
                'last_seven_days' => 27,
                'distinct_actors' => 2,
            ]);
    }

    public function test_action_filter_lists_supported_actions_by_area_even_when_absent_from_history(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Customer updated')
            ->assertSee('Customer note added')
            ->assertSee('Customer account registered')
            ->assertSee('Customer account linked')
            ->assertSee('Customer account link conflict');
    }

    public function test_search_matches_action_actor_and_booking_reference(): void
    {
        $manager = $this->userWithRole('manager', 'Morgan Searcher');
        $vehicle = $this->vehicle('audit-search-car');
        $customer = $this->customer('Audit Search Customer', 'audit-search@example.test');
        $booking = $this->booking($vehicle, $customer, 'FG-AUDIT-REFERENCE-7');
        $this->audit(
            $manager,
            'booking_approved',
            ['from' => 'pending', 'to' => 'approved'],
            Booking::class,
            (string) $booking->id,
        );

        $this->actingAs($manager)
            ->get(route('admin.audit-logs.index', ['search' => 'booking_approved']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1);

        $this->get(route('admin.audit-logs.index', ['search' => 'Morgan Searcher']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1);

        $this->get(route('admin.audit-logs.index', ['search' => 'FG-AUDIT-REFERENCE-7']))
            ->assertOk()
            ->assertSee('FG-AUDIT-REFERENCE-7')
            ->assertSee(route('admin.bookings.show', $booking), false)
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1);
    }

    public function test_action_actor_and_date_filters_combine_correctly(): void
    {
        $admin = $this->userWithRole('admin', 'Ada Filters');
        $manager = $this->userWithRole('manager', 'Morgan Filters');

        $this->audit($admin, 'customer_updated', ['full_name' => ['from' => 'Old', 'to' => 'New']], Customer::class, '101', Carbon::parse('2026-11-14 08:00:00', 'Asia/Manila'));
        $this->audit($manager, 'booking_approved', ['from' => 'pending', 'to' => 'approved'], Booking::class, '202', Carbon::parse('2026-11-13 08:00:00', 'Asia/Manila'));
        $this->audit($manager, 'report_exported', ['report_type' => 'admin_reports'], User::class, (string) $manager->id, Carbon::parse('2026-10-01 08:00:00', 'Asia/Manila'));

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'customer_updated']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1);

        $this->get(route('admin.audit-logs.index', ['actor' => $manager->id]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 2);

        $this->get(route('admin.audit-logs.index', [
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-14',
        ]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 2);

        $this->get(route('admin.audit-logs.index', [
            'action' => 'booking_approved',
            'actor' => $manager->id,
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-14',
        ]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1);
    }

    public function test_removed_maintenance_entry_links_to_its_viewable_vehicle(): void
    {
        $manager = $this->userWithRole('manager');
        $vehicle = $this->vehicle('audit-maintenance-link');
        $this->audit(
            $manager,
            'vehicle_unavailability_removed',
            ['vehicle_id' => $vehicle->id],
            VehicleUnavailability::class,
            '909',
        );

        $this->actingAs($manager)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Maintenance period #909')
            ->assertSee($vehicle->name)
            ->assertSee(route('admin.fleet.show', $vehicle), false);
    }

    public function test_filter_chips_remove_only_their_filter_and_keep_the_rest(): void
    {
        $manager = $this->userWithRole('manager', 'Morgan Chips');
        $this->audit($manager, 'booking_approved', ['from' => 'pending', 'to' => 'approved']);
        $filters = [
            'search' => 'needle',
            'action' => 'booking_approved',
            'actor' => $manager->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
        ];

        $response = $this->actingAs($manager)
            ->get(route('admin.audit-logs.index', $filters))
            ->assertOk()
            ->assertSee('Remove Search: needle filter')
            ->assertSee('Remove Action: Reservation approved filter')
            ->assertSee('Remove Actor: Morgan Chips filter')
            ->assertSee('Remove Date: Nov 1, 2026 – Nov 10, 2026 filter');

        $response->assertSee(e(route('admin.audit-logs.index', [
            'action' => 'booking_approved',
            'actor' => $manager->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
        ])), false)
            ->assertSee(e(route('admin.audit-logs.index', [
                'search' => 'needle',
                'actor' => $manager->id,
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-10',
            ])), false)
            ->assertSee(e(route('admin.audit-logs.index', [
                'search' => 'needle',
                'action' => 'booking_approved',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-10',
            ])), false)
            ->assertSee(e(route('admin.audit-logs.index', [
                'search' => 'needle',
                'action' => 'booking_approved',
                'actor' => $manager->id,
            ])), false);
    }

    public function test_date_range_validation_rejects_reversed_partial_and_oversized_ranges(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.audit-logs.index', [
                'start_date' => '2026-11-10',
                'end_date' => '2026-11-01',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->get(route('admin.audit-logs.index', [
            'start_date' => '2026-11-01',
        ]))->assertSessionHasErrors('end_date');

        $this->get(route('admin.audit-logs.index', [
            'start_date' => '2025-01-01',
            'end_date' => '2026-11-15',
        ]))->assertSessionHasErrors('end_date');
    }

    public function test_manager_sees_masked_contacts_and_redacted_secrets_with_escaped_payload_values(): void
    {
        $manager = $this->userWithRole('manager', 'Morgan Privacy');
        $customer = $this->customer('Private Customer', 'private@example.test');

        $this->audit($manager, 'customer_updated', [
            'email' => ['from' => 'old.private@example.test', 'to' => 'new.private@example.test'],
            'mobile_number' => ['from' => '09171112222', 'to' => '09173334444'],
            'password' => 'stored-password-value',
            'nested' => ['access_token' => 'stored-token-value'],
            'reason' => 'Contact old.private@example.test for review.',
            'note' => '<script>alert(1)</script>',
        ], Customer::class, (string) $customer->id);

        $this->actingAs($manager)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('[MASKED]')
            ->assertSee('[REDACTED]')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('old.private@example.test')
            ->assertDontSee('new.private@example.test')
            ->assertDontSee('09171112222')
            ->assertDontSee('09173334444')
            ->assertDontSee('stored-password-value')
            ->assertDontSee('stored-token-value')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_administrator_can_view_contact_values_and_empty_state_is_read_only(): void
    {
        $admin = $this->userWithRole('admin', 'Ada Privacy');
        $customer = $this->customer('Admin Visible Customer', 'admin-visible@example.test');
        $audit = $this->audit($admin, 'customer_updated', [
            'email' => ['from' => 'old@example.test', 'to' => 'admin-visible@example.test'],
        ], Customer::class, (string) $customer->id);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('old@example.test')
            ->assertSee('admin-visible@example.test');

        $audit->delete();
        $before = AuditLog::query()->count();

        $this->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('No audit entries found')
            ->assertSee('Showing 0–0 of 0')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['total'] === 0);

        $this->assertSame($before, AuditLog::query()->count());
    }

    public function test_audit_page_reads_only_and_does_not_change_business_record_counts(): void
    {
        $manager = $this->userWithRole('manager');
        $vehicle = $this->vehicle('audit-read-only-car');
        $customer = $this->customer('Read Only Customer', 'read-only@example.test');
        $this->booking($vehicle, $customer, 'FG-AUDIT-READONLY');
        $this->audit($manager, 'booking_submitted', ['vehicle_id' => $vehicle->id], Booking::class, '1');

        $before = [
            'audit_logs' => AuditLog::query()->count(),
            'customers' => Customer::query()->count(),
            'bookings' => Booking::query()->count(),
            'vehicles' => Vehicle::query()->count(),
        ];

        $this->actingAs($manager)
            ->get(route('admin.audit-logs.index'))
            ->assertOk();

        $this->assertSame($before, [
            'audit_logs' => AuditLog::query()->count(),
            'customers' => Customer::query()->count(),
            'bookings' => Booking::query()->count(),
            'vehicles' => Vehicle::query()->count(),
        ]);
    }

    private function userWithRole(string $role, ?string $name = null): User
    {
        $user = User::factory()->create(['name' => $name ?? fake()->name()]);
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function audit(
        User $actor,
        string $action,
        array $changes,
        string $subjectType = User::class,
        string $subjectId = '1',
        ?Carbon $createdAt = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    private function customer(string $name, string $email): Customer
    {
        return Customer::query()->forceCreate([
            'full_name' => $name,
            'email' => $email,
            'mobile_number' => '09171234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function vehicle(string $id): Vehicle
    {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => 'Audit '.$id,
            'brand' => 'FleetGuard',
            'model' => 'Audit Test',
            'category' => 'sedan',
            'description' => 'Audit test vehicle.',
            'daily_rate' => 2500,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    private function booking(Vehicle $vehicle, Customer $customer, string $reference): Booking
    {
        return Booking::query()->create([
            'booking_reference' => $reference,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-11-20 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-11-21 09:00:00',
            'rental_duration_days' => 1,
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 2500,
            'status' => Booking::STATUS_PENDING,
        ]);
    }
}
