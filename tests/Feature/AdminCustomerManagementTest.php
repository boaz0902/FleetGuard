<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
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

    public function test_customer_directory_access_is_role_limited_and_masks_contact_for_view_only_roles(): void
    {
        $customer = $this->customer([
            'full_name' => 'Alex Customer',
            'email' => 'alex.customer@example.test',
            'mobile_number' => '09171234567',
        ]);
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customerUser = $this->userWithRole('customer');

        $this->get(route('admin.customers.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($customerUser)->get(route('admin.customers.index'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('alex.customer@example.test')
            ->assertSee('09171234567')
            ->assertSee(route('admin.customers.edit', $customer), false);

        foreach ([$manager, $staff] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('admin.customers.index'))
                ->assertOk()
                ->assertSee('Alex Customer')
                ->assertSee('a***@example.test')
                ->assertSee('•••• 4567')
                ->assertDontSee('alex.customer@example.test')
                ->assertDontSee('09171234567')
                ->assertDontSee(route('admin.customers.edit', $customer), false);
        }

        $this->actingAs($staff)
            ->get(route('admin.customers.show', $customer).'?tab=bookings')
            ->assertOk()
            ->assertSee('Booking history is restricted');
    }

    public function test_customer_directory_searches_filters_sorts_and_paginates_records(): void
    {
        $admin = $this->userWithRole('admin');
        $customers = [];

        for ($index = 1; $index <= 12; $index++) {
            $customers[] = $this->customer([
                'full_name' => sprintf('Directory %02d', $index),
                'email' => sprintf('directory%02d@example.test', $index),
                'mobile_number' => sprintf('0917123%04d', $index),
            ]);
        }

        $pending = $this->customer(['full_name' => 'Pending Activity']);
        $this->booking($pending, ['status' => Booking::STATUS_PENDING]);
        $upcoming = $this->customer(['full_name' => 'Upcoming Activity']);
        $this->booking($upcoming, ['status' => Booking::STATUS_APPROVED]);
        $ongoing = $this->customer(['full_name' => 'Ongoing Activity']);
        $this->booking($ongoing, ['status' => Booking::STATUS_ONGOING]);
        $completed = $this->customer(['full_name' => 'Completed Activity']);
        $this->booking($completed, ['status' => Booking::STATUS_COMPLETED]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['sort' => 'full_name', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('Showing 1–10 of 16')
            ->assertSeeInOrder(['Directory 01', 'Directory 02'])
            ->assertViewHas('customers', fn ($page) => $page->total() === 16);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['sort' => 'full_name', 'direction' => 'asc', 'page' => 2]))
            ->assertOk()
            ->assertSee('Directory 12')
            ->assertDontSee('Directory 01');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['search' => 'directory05@example.test']))
            ->assertOk()
            ->assertSee('Directory 05')
            ->assertDontSee('Directory 04')
            ->assertViewHas('customers', fn ($page) => $page->total() === 1);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['search' => 'Directory 04']))
            ->assertOk()
            ->assertSee('Directory 04')
            ->assertViewHas('customers', fn ($page) => $page->total() === 1);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['search' => $customers[2]->mobile_number]))
            ->assertOk()
            ->assertSee('Directory 03')
            ->assertViewHas('customers', fn ($page) => $page->total() === 1);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['activity' => 'none']))
            ->assertOk()
            ->assertDontSee('Pending Activity')
            ->assertViewHas('customers', fn ($page) => $page->total() === 12);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['activity' => 'pending']))
            ->assertOk()
            ->assertSee('Pending Activity')
            ->assertViewHas('customers', fn ($page) => $page->total() === 1);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['activity' => 'pending', 'joined' => 'month']))
            ->assertOk()
            ->assertSee(route('admin.customers.index', ['joined' => 'month']), false)
            ->assertSee(route('admin.customers.index', ['activity' => 'pending']), false);

        foreach ([
            'upcoming' => 'Upcoming Activity',
            'ongoing' => 'Ongoing Activity',
            'completed' => 'Completed Activity',
        ] as $activity => $name) {
            $this->actingAs($admin)
                ->get(route('admin.customers.index', ['activity' => $activity]))
                ->assertOk()
                ->assertSee($name)
                ->assertViewHas('customers', fn ($page) => $page->total() === 1);
        }

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['joined' => 'month']))
            ->assertOk()
            ->assertViewHas('customers', fn ($page) => $page->total() === 16);
    }

    public function test_metrics_are_aggregated_from_customer_and_booking_records(): void
    {
        $admin = $this->userWithRole('admin');
        $pending = $this->customer();
        $upcoming = $this->customer();
        $ongoing = $this->customer();
        $completed = $this->customer();
        $rejected = $this->customer();
        $cancelled = $this->customer();
        $this->booking($pending, ['status' => Booking::STATUS_PENDING]);
        $this->booking($upcoming, ['status' => Booking::STATUS_APPROVED]);
        $this->booking($ongoing, ['status' => Booking::STATUS_ONGOING]);
        $this->booking($completed, ['status' => Booking::STATUS_COMPLETED]);
        $this->booking($rejected, ['status' => Booking::STATUS_REJECTED]);
        $this->booking($cancelled, ['status' => Booking::STATUS_CANCELLED]);

        $response = $this->actingAs($admin)->get(route('admin.customers.index'));

        $response->assertOk()->assertViewHas('metrics', function (array $metrics): bool {
            return $metrics['total'] === 6
                && $metrics['pending'] === 1
                && $metrics['upcoming'] === 1
                && $metrics['ongoing'] === 1
                && $metrics['completed'] === 1
                && $metrics['blocked'] === 0
                && $metrics['without_bookings'] === 0
                && array_sum($metrics['booking_summary']) === 6;
        });
    }

    public function test_admin_can_add_append_only_internal_notes_with_audited_note_ids(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.customers.notes.store', $customer), ['body' => "  <script>private note</script>\n"])
            ->assertRedirect(route('admin.customers.show', [$customer, 'tab' => 'notes']));
        $firstNote = $customer->notes()->firstOrFail();
        $this->assertSame('<script>private note</script>', $firstNote->body);
        $this->assertSame($admin->id, $firstNote->author_id);

        $this->actingAs($admin)
            ->post(route('admin.customers.notes.store', $customer), ['body' => 'Second internal note'])
            ->assertRedirect(route('admin.customers.show', [$customer, 'tab' => 'notes']));

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer).'?tab=notes')
            ->assertOk()
            ->assertSeeInOrder(['Second internal note', '&lt;script&gt;private note&lt;/script&gt;'], false)
            ->assertDontSee('<script>private note</script>', false);

        foreach ([$manager, $staff] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('admin.customers.show', $customer).'?tab=notes')
                ->assertOk()
                ->assertSee('Second internal note');

            $this->post(route('admin.customers.notes.store', $customer), ['body' => 'Not authorized'])
                ->assertForbidden();
        }

        $this->actingAs($admin)
            ->from(route('admin.customers.show', [$customer, 'tab' => 'notes']))
            ->post(route('admin.customers.notes.store', $customer), ['body' => " \n\t "])
            ->assertSessionHasErrors('body');

        $logs = AuditLog::query()
            ->where('action', 'customer_note_added')
            ->where('subject_type', Customer::class)
            ->where('subject_id', (string) $customer->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['note_id' => $firstNote->id], $logs[0]->changes);
        $this->assertSame($admin->id, $logs[0]->user_id);
        $this->assertStringNotContainsString('private note', json_encode($logs[0]->changes));
        $this->assertSame(2, $customer->notes()->count());
    }

    public function test_customer_blocking_is_admin_only_audited_and_does_not_change_existing_bookings(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->customer(['full_name' => 'Blocked Example']);
        $bookingStatuses = [
            Booking::STATUS_PENDING,
            Booking::STATUS_APPROVED,
            Booking::STATUS_ONGOING,
            Booking::STATUS_COMPLETED,
        ];
        $bookings = collect($bookingStatuses)
            ->map(fn (string $status) => $this->booking($customer, ['status' => $status]));
        $before = $bookings->map(fn (Booking $booking) => [
            'id' => $booking->id,
            'status' => $booking->status,
            'pickup_datetime' => $booking->pickup_datetime->toDateTimeString(),
            'return_datetime' => $booking->return_datetime->toDateTimeString(),
            'daily_rate_snapshot' => $booking->daily_rate_snapshot,
            'estimated_total' => $booking->estimated_total,
        ])->all();

        $this->actingAs($manager)
            ->post(route('admin.customers.block', $customer), ['reason' => 'Not authorized'])
            ->assertForbidden();
        $this->actingAs($staff)
            ->post(route('admin.customers.unblock', $customer))
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.customers.show', $customer))
            ->post(route('admin.customers.block', $customer), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(Customer::STATUS_ACTIVE, $customer->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'customer_blocked']);

        $this->actingAs($admin)
            ->post(route('admin.customers.block', $customer), ['reason' => 'Repeated abusive requests'])
            ->assertRedirect(route('admin.customers.show', $customer));
        $this->assertSame(Customer::STATUS_BLOCKED, $customer->fresh()->status);
        $this->assertSame(1, Customer::query()->where('status', Customer::STATUS_BLOCKED)->count());

        $blockedLog = AuditLog::query()
            ->where('action', 'customer_blocked')
            ->where('subject_id', (string) $customer->id)
            ->firstOrFail();
        $this->assertSame($admin->id, $blockedLog->user_id);
        $this->assertSame([
            'from' => Customer::STATUS_ACTIVE,
            'to' => Customer::STATUS_BLOCKED,
            'reason' => 'Repeated abusive requests',
        ], $blockedLog->changes);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['status' => 'blocked']))
            ->assertOk()
            ->assertSee('Blocked Example')
            ->assertSee('Status: Blocked')
            ->assertSee('Blocked customers');

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Repeated abusive requests')
            ->assertSee($admin->name);

        $this->actingAs($manager)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Blocked')
            ->assertDontSee('Repeated abusive requests')
            ->assertDontSee('Block customer');

        $after = $customer->bookings()->orderBy('id')->get()->map(fn (Booking $booking) => [
            'id' => $booking->id,
            'status' => $booking->status,
            'pickup_datetime' => $booking->pickup_datetime->toDateTimeString(),
            'return_datetime' => $booking->return_datetime->toDateTimeString(),
            'daily_rate_snapshot' => $booking->daily_rate_snapshot,
            'estimated_total' => $booking->estimated_total,
        ])->all();
        $this->assertSame($before, $after);

        $this->actingAs($admin)
            ->post(route('admin.customers.unblock', $customer))
            ->assertRedirect(route('admin.customers.show', $customer));
        $this->assertSame(Customer::STATUS_ACTIVE, $customer->fresh()->status);

        $unblockedLog = AuditLog::query()
            ->where('action', 'customer_unblocked')
            ->where('subject_id', (string) $customer->id)
            ->firstOrFail();
        $this->assertSame($admin->id, $unblockedLog->user_id);
        $this->assertSame([
            'from' => Customer::STATUS_BLOCKED,
            'to' => Customer::STATUS_ACTIVE,
            'reason' => 'Repeated abusive requests',
        ], $unblockedLog->changes);
    }

    public function test_customer_details_show_booking_snapshots_and_authorized_activity(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $customer = $this->customer([
            'full_name' => 'Rental Customer',
            'email' => 'rental@example.test',
            'mobile_number' => '09171112222',
        ]);
        $booking = $this->booking($customer, [
            'status' => Booking::STATUS_COMPLETED,
            'daily_rate_snapshot' => '2500.00',
            'estimated_total' => '7500.00',
        ]);
        $booking->auditLogs()->create([
            'user_id' => $manager->id,
            'action' => 'booking_approved',
            'changes' => ['from' => Booking::STATUS_PENDING, 'to' => Booking::STATUS_APPROVED],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer).'?tab=bookings')
            ->assertOk()
            ->assertSee('Rental Customer')
            ->assertSee('rental@example.test')
            ->assertSee('09171112222')
            ->assertSee($booking->booking_reference)
            ->assertSee('₱2,500.00 / day')
            ->assertSee('₱7,500.00')
            ->assertSee(route('admin.bookings.show', $booking), false)
            ->assertSee('Lifetime booking value');

        $this->actingAs($manager)
            ->get(route('admin.customers.show', $customer).'?tab=activity')
            ->assertOk()
            ->assertSee('Reservation approved')
            ->assertSee($manager->name)
            ->assertSee('r***@example.test')
            ->assertDontSee('rental@example.test');
    }

    public function test_admin_can_edit_supported_fields_and_audit_without_changing_booking_snapshots(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->customer([
            'full_name' => 'Before Update',
            'email' => 'before@example.test',
            'mobile_number' => '09171234567',
        ]);
        $booking = $this->booking($customer, [
            'daily_rate_snapshot' => '2500.00',
            'estimated_total' => '7500.00',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.customers.update', $customer), [
                'full_name' => 'After Update',
                'email' => 'AFTER@example.test',
                'mobile_number' => '+639171234567',
            ])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHas('status', 'Customer profile updated.');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'full_name' => 'After Update',
            'email' => 'after@example.test',
            'mobile_number' => '+639171234567',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'customer_id' => $customer->id,
            'daily_rate_snapshot' => '2500.00',
            'estimated_total' => '7500.00',
        ]);

        $audit = AuditLog::query()
            ->where('action', 'customer_updated')
            ->where('subject_type', Customer::class)
            ->where('subject_id', (string) $customer->id)
            ->firstOrFail();

        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('before@example.test', $audit->changes['email']['from']);
        $this->assertSame('after@example.test', $audit->changes['email']['to']);
        $this->assertSame('After Update', $audit->changes['full_name']['to']);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer).'?tab=activity')
            ->assertOk()
            ->assertSee('Customer profile updated');
    }

    public function test_customer_edit_validation_and_admin_only_authorization(): void
    {
        $customer = $this->customer();
        $duplicate = $this->customer();
        $manager = $this->userWithRole('manager');
        $admin = $this->userWithRole('admin');

        $this->actingAs($manager)
            ->get(route('admin.customers.edit', $customer))
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('admin.customers.update', $customer), [
                'full_name' => 'Changed Name',
                'email' => $customer->email,
                'mobile_number' => $customer->mobile_number,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.customers.edit', $customer))
            ->put(route('admin.customers.update', $customer), [
                'full_name' => 'Valid Name',
                'email' => $duplicate->email,
                'mobile_number' => 'invalid-phone',
            ])
            ->assertSessionHasErrors(['email', 'mobile_number']);

        $this->assertSame(0, AuditLog::query()->where('action', 'customer_updated')->count());
        $this->assertSame(0, $customer->fresh()->bookings()->count());
    }

    public function test_no_change_does_not_create_a_customer_audit_entry(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->customer();

        $this->actingAs($admin)
            ->put(route('admin.customers.update', $customer), [
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'mobile_number' => $customer->mobile_number,
            ])
            ->assertSessionHas('status', 'No changes were made to the customer record.');

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'customer_updated',
            'subject_type' => Customer::class,
            'subject_id' => (string) $customer->id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::query()->create(array_replace([
            'full_name' => 'Customer '.Str::random(8),
            'email' => 'customer-'.Str::lower(Str::random(12)).'@example.test',
            'mobile_number' => '09171234567',
        ], $attributes));
    }

    private function booking(Customer $customer, array $attributes = []): Booking
    {
        $pickup = Carbon::now()->addDays(5)->startOfHour();
        $return = $pickup->copy()->addDays(3);

        return Booking::query()->create(array_replace([
            'booking_reference' => 'FG-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => 'toyota-vios',
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => $pickup,
            'return_location' => 'Panabo City',
            'return_datetime' => $return,
            'rental_duration_days' => 3,
            'daily_rate_snapshot' => '2500.00',
            'estimated_total' => '7500.00',
            'status' => Booking::STATUS_PENDING,
        ], $attributes));
    }
}
