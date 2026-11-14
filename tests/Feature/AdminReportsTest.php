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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReportsTest extends TestCase
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

    public function test_report_access_matches_admin_manager_permissions_and_hides_staff_navigation(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('customer');

        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('Reports & Analytics');
        $this->actingAs($manager)->get(route('admin.reports.index'))->assertOk()->assertSee('Export CSV');

        $this->actingAs($staff)
            ->get(route('admin.fleet.index'))
            ->assertOk()
            ->assertDontSee(route('admin.reports.index'));
        $this->actingAs($staff)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.reports.export'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.reports.index'))->assertForbidden();
        $this->app['auth']->logout();
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    }

    public function test_overview_uses_booking_snapshots_and_excludes_pending_from_confirmed_values(): void
    {
        $manager = $this->userWithRole('manager');
        $sedan = $this->vehicle('report-sedan', 'sedan', 2500);
        $suv = $this->vehicle('report-suv', 'suv', 5500);
        $customer = $this->customer('Report Customer', 'report@example.test', '2026-10-10 09:00:00');

        $this->booking($sedan, $customer, Booking::STATUS_PENDING, [
            'booking_reference' => 'FG-REPORT-PENDING',
            'pickup_datetime' => '2026-11-01 09:00:00',
            'return_datetime' => '2026-11-02 09:00:00',
            'rental_duration_days' => 1,
            'daily_rate_snapshot' => 900,
            'estimated_total' => 900,
        ]);
        $this->booking($sedan, $customer, Booking::STATUS_APPROVED, [
            'booking_reference' => 'FG-REPORT-APPROVED',
            'pickup_datetime' => '2026-11-02 09:00:00',
            'return_datetime' => '2026-11-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 1000,
            'estimated_total' => 2000,
        ]);
        $this->booking($sedan, $customer, Booking::STATUS_ONGOING, [
            'booking_reference' => 'FG-REPORT-ONGOING',
            'pickup_datetime' => '2026-11-03 09:00:00',
            'return_datetime' => '2026-11-04 09:00:00',
            'rental_duration_days' => 3,
            'daily_rate_snapshot' => 1500,
            'estimated_total' => 4500,
        ]);
        $this->booking($sedan, $customer, Booking::STATUS_COMPLETED, [
            'booking_reference' => 'FG-REPORT-COMPLETED',
            'pickup_datetime' => '2026-11-04 09:00:00',
            'return_datetime' => '2026-11-05 09:00:00',
            'rental_duration_days' => 4,
            'daily_rate_snapshot' => 2000,
            'estimated_total' => 8000,
        ]);
        $this->booking($sedan, $customer, Booking::STATUS_REJECTED, [
            'booking_reference' => 'FG-REPORT-REJECTED',
            'pickup_datetime' => '2026-11-05 09:00:00',
        ]);
        $this->booking($sedan, $customer, Booking::STATUS_CANCELLED, [
            'booking_reference' => 'FG-REPORT-CANCELLED',
            'pickup_datetime' => '2026-11-06 09:00:00',
        ]);
        $this->booking($suv, $customer, Booking::STATUS_APPROVED, [
            'booking_reference' => 'FG-REPORT-OTHER-CATEGORY',
            'pickup_datetime' => '2026-11-07 09:00:00',
            'daily_rate_snapshot' => 5000,
            'estimated_total' => 10000,
        ]);

        $sedan->update(['daily_rate' => 99000]);

        $response = $this->actingAs($manager)->get(route('admin.reports.index', [
            'range' => 'custom',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'category' => 'sedan',
        ]));

        $response->assertOk()
            ->assertSee('Estimated Revenue (from booking snapshots)')
            ->assertSee('Pending requests are included in reservation counts, but not confirmed revenue or utilization.')
            ->assertSee('report-reservations')
            ->assertViewHas('overview', function (array $metrics): bool {
                return $metrics['total_reservations'] === 6
                    && $metrics['approved'] === 1
                    && $metrics['completed'] === 1
                    && $metrics['cancelled_rejected'] === 2
                    && $metrics['approval_rate'] === 75.0
                    && $metrics['estimated_revenue'] === 14500.0
                    && $metrics['completed_estimated_revenue'] === 8000.0
                    && $metrics['average_duration_days'] === 3.0
                    && $metrics['average_daily_rate'] === 1500.0;
            });

        $this->actingAs($manager)
            ->get(route('admin.reports.index', [
                'range' => 'custom',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
                'vehicle_id' => $suv->id,
            ]))
            ->assertOk()
            ->assertViewHas('overview', fn (array $metrics): bool => $metrics['total_reservations'] === 1
                && $metrics['estimated_revenue'] === 10000.0);
    }

    public function test_utilization_clips_intervals_respects_exclusive_end_merges_maintenance_and_caps_at_one_hundred(): void
    {
        $manager = $this->userWithRole('manager');
        $vehicle = $this->vehicle('utilization-car', 'sedan');
        $customer = $this->customer('Utilization Customer', 'utilization@example.test');

        $this->booking($vehicle, $customer, Booking::STATUS_COMPLETED, [
            'booking_reference' => 'FG-UTIL-CLIPPED',
            'pickup_datetime' => '2026-10-31 12:00:00',
            'return_datetime' => '2026-11-02 12:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 1000,
            'estimated_total' => 2000,
        ]);
        $this->booking($vehicle, $customer, Booking::STATUS_APPROVED, [
            'booking_reference' => 'FG-UTIL-END',
            'pickup_datetime' => '2026-11-04 00:00:00',
            'return_datetime' => '2026-11-05 00:00:00',
            'rental_duration_days' => 1,
        ]);
        $this->booking($vehicle, $customer, Booking::STATUS_COMPLETED, [
            'booking_reference' => 'FG-UTIL-OVERLAP',
            'pickup_datetime' => '2026-11-04 00:00:00',
            'return_datetime' => '2026-11-05 00:00:00',
            'rental_duration_days' => 1,
        ]);
        $this->booking($vehicle, $customer, Booking::STATUS_APPROVED, [
            'booking_reference' => 'FG-UTIL-EXCLUSIVE-BOUNDARY',
            'pickup_datetime' => '2026-11-05 00:00:00',
            'return_datetime' => '2026-11-06 00:00:00',
        ]);
        $this->booking($vehicle, $customer, Booking::STATUS_PENDING, [
            'booking_reference' => 'FG-UTIL-PENDING',
            'pickup_datetime' => '2026-11-01 00:00:00',
            'return_datetime' => '2026-11-03 00:00:00',
        ]);

        VehicleUnavailability::query()->create([
            'vehicle_id' => $vehicle->id,
            'start_datetime' => '2026-11-01 00:00:00',
            'end_datetime' => '2026-11-02 00:00:00',
            'reason' => 'Routine maintenance',
        ]);
        VehicleUnavailability::query()->create([
            'vehicle_id' => $vehicle->id,
            'start_datetime' => '2026-11-01 12:00:00',
            'end_datetime' => '2026-11-02 12:00:00',
            'reason' => 'Overlapping maintenance fixture',
        ]);

        $response = $this->actingAs($manager)->get(route('admin.reports.index', [
            'range' => 'custom',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-04',
            'vehicle_id' => $vehicle->id,
        ]));

        $response->assertOk()
            ->assertViewHas('overview', fn (array $metrics): bool => $metrics['total_reservations'] === 3
                && $metrics['estimated_revenue'] > 0)
            ->assertViewHas('fleet', function (array $fleet): bool {
                $vehicle = $fleet['vehicles']->first();

                return $fleet['range_days'] === 4
                    && $vehicle['booking_count'] === 3
                    && $vehicle['maintenance_days'] === 1.5
                    && $vehicle['available_days'] === 2.5
                    && $vehicle['rented_days'] === 2.5
                    && $vehicle['utilization'] === 100.0
                    && $vehicle['idle_days'] === 0.0;
            });
    }

    public function test_filters_validate_custom_ranges_and_vehicle_category_combinations(): void
    {
        $manager = $this->userWithRole('manager');
        $sedan = $this->vehicle('filter-sedan', 'sedan');
        $this->vehicle('filter-suv', 'suv');
        $this->actingAs($manager);

        $this->getJson(route('admin.reports.index', [
            'range' => 'custom',
            'start_date' => '2026-11-30',
            'end_date' => '2026-11-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');

        $this->getJson(route('admin.reports.index', [
            'range' => 'custom',
            'start_date' => '2025-01-01',
            'end_date' => '2026-01-02',
        ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');

        $this->getJson(route('admin.reports.index', ['category' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->getJson(route('admin.reports.index', ['vehicle_id' => 'not-a-vehicle']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        $this->getJson(route('admin.reports.index', [
            'category' => 'suv',
            'vehicle_id' => $sedan->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    }

    public function test_empty_and_single_record_reports_render_without_division_errors(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('No bookings in this range.')
            ->assertSee('No vehicles match these filters.')
            ->assertSee('No maintenance days in this range.')
            ->assertSee('Reservation status breakdown:', false)
            ->assertSee('role="img"', false)
            ->assertViewHas('overview', fn (array $metrics): bool => $metrics['total_reservations'] === 0
                && $metrics['estimated_revenue'] === 0.0
                && $metrics['fleet_utilization'] === 0.0);

        $vehicle = $this->vehicle('single-report-car', 'sedan');
        $customer = $this->customer('Single Record', 'single@example.test', '2026-11-10 09:00:00');
        $this->booking($vehicle, $customer, Booking::STATUS_PENDING, [
            'booking_reference' => 'FG-REPORT-SINGLE',
            'pickup_datetime' => '2026-11-14 10:00:00',
            'return_datetime' => '2026-11-15 10:00:00',
            'estimated_total' => 4000,
            'daily_rate_snapshot' => 4000,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.reports.index', ['range' => 'custom', 'start_date' => '2026-11-14', 'end_date' => '2026-11-14']))
            ->assertOk()
            ->assertViewHas('overview', fn (array $metrics): bool => $metrics['total_reservations'] === 1
                && $metrics['estimated_revenue'] === 0.0
                && $metrics['average_duration_days'] === null)
            ->assertViewHas('trend', fn (array $trend): bool => count($trend) === 1
                && $trend[0]['count'] === 1)
            ->assertSee('admin-report-trend-point', false)
            ->assertSee('Reservation trend has 1 booking', false)
            ->assertSee('aria-label="Completed-only estimated revenue', false);
    }

    public function test_actual_rental_length_uses_only_paired_audit_timestamps(): void
    {
        $manager = $this->userWithRole('manager');
        $vehicle = $this->vehicle('audit-time-car', 'sedan');
        $customer = $this->customer('Audit Time Customer', 'audit-time@example.test');
        $completed = $this->booking($vehicle, $customer, Booking::STATUS_COMPLETED, [
            'booking_reference' => 'FG-AUDIT-TIME',
            'pickup_datetime' => '2026-11-10 09:00:00',
            'return_datetime' => '2026-11-12 09:00:00',
        ]);
        $completed->auditLogs()->createMany([
            [
                'user_id' => $manager->id,
                'action' => 'rental_checked_out',
                'changes' => ['checked_out_at' => '2026-11-10T09:00:00+08:00'],
                'created_at' => Carbon::parse('2026-11-10 09:00:00', 'Asia/Manila'),
            ],
            [
                'user_id' => $manager->id,
                'action' => 'rental_checked_in',
                'changes' => ['checked_in_at' => '2026-11-11T21:00:00+08:00'],
                'created_at' => Carbon::parse('2026-11-11 21:00:00', 'Asia/Manila'),
            ],
        ]);

        $this->actingAs($manager)
            ->get(route('admin.reports.index', [
                'range' => 'custom',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
            ]))
            ->assertOk()
            ->assertViewHas('operations', fn (array $operations): bool => $operations['paired_audit_rentals'] === 1
                && abs($operations['average_actual_rental_days'] - 1.5) < 0.001);
    }

    public function test_customer_popularity_maintenance_and_rental_sections_use_filtered_data(): void
    {
        $manager = $this->userWithRole('manager');
        $sedan = $this->vehicle('popular-sedan', 'sedan');
        $suv = $this->vehicle('popular-suv', 'suv');
        $this->vehicle('current-maintenance', 'sedan', 3000, Vehicle::STATUS_MAINTENANCE);
        $newCustomer = $this->customer('New Customer', 'new-customer@example.test', '2026-11-05 08:00:00');
        $returningCustomer = $this->customer('Returning Customer', 'returning@example.test', '2026-10-05 08:00:00');
        $pendingCustomer = $this->customer('Pending Customer', 'pending-customer@example.test', '2026-11-07 08:00:00');

        $this->booking($sedan, $newCustomer, Booking::STATUS_COMPLETED, [
            'booking_reference' => 'FG-POPULAR-NEW',
            'pickup_datetime' => '2026-11-05 09:00:00',
            'return_datetime' => '2026-11-07 09:00:00',
            'rental_duration_days' => 2,
        ]);
        $this->booking($sedan, $returningCustomer, Booking::STATUS_COMPLETED, [
            'booking_reference' => 'FG-POPULAR-RETURN',
            'pickup_datetime' => '2026-11-06 09:00:00',
            'return_datetime' => '2026-11-07 09:00:00',
        ]);
        $this->booking($suv, $returningCustomer, Booking::STATUS_ONGOING, [
            'booking_reference' => 'FG-POPULAR-OVERDUE',
            'pickup_datetime' => '2026-11-07 09:00:00',
            'return_datetime' => '2026-11-08 09:00:00',
        ]);
        $this->booking($sedan, $pendingCustomer, Booking::STATUS_PENDING, [
            'booking_reference' => 'FG-POPULAR-PENDING',
            'pickup_datetime' => '2026-11-07 10:00:00',
        ]);
        VehicleUnavailability::query()->create([
            'vehicle_id' => $sedan->id,
            'start_datetime' => '2026-11-06 00:00:00',
            'end_datetime' => '2026-11-07 00:00:00',
            'reason' => 'Scheduled service',
        ]);
        VehicleUnavailability::query()->create([
            'vehicle_id' => $sedan->id,
            'start_datetime' => '2026-11-06 12:00:00',
            'end_datetime' => '2026-11-07 12:00:00',
            'reason' => 'Overlapping service window',
        ]);

        $response = $this->actingAs($manager)->get(route('admin.reports.index', [
            'range' => 'custom',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-07',
        ]));

        $response->assertOk()->assertViewHas('customers');
        $customers = $response->viewData('customers');
        $this->assertSame(3, $customers['total_customers']);
        $this->assertSame(2, $customers['new_records']);
        $this->assertSame(1, $customers['returning_customers']);
        $this->assertSame('New Customer', $customers['top_customers']->first()->full_name);
        $this->assertSame(1, (int) $customers['top_customers']->first()->completed_bookings);

        $response
            ->assertViewHas('popular_vehicles', function ($vehicles): bool {
                return $vehicles->first()->name === 'popular-sedan'
                    && $vehicles->first()->booking_count === 2
                    && (int) $vehicles->first()->rented_days === 3;
            })
            ->assertViewHas('popular_categories', function ($categories): bool {
                return $categories->first()->category === 'sedan'
                    && $categories->first()->booking_count === 2;
            })
            ->assertViewHas('maintenance', function (array $maintenance): bool {
                return $maintenance['period_count'] === 2
                    && $maintenance['days'] === 1.5
                    && $maintenance['vehicles_currently_under_maintenance'] === 1;
            })
            ->assertViewHas('operations', fn (array $operations): bool => $operations['completed_rentals'] === 2
                && $operations['overdue_returns'] === 1);
    }

    public function test_csv_export_is_masked_injection_safe_audited_and_read_only_for_business_records(): void
    {
        $admin = $this->userWithRole('admin');
        $vehicle = $this->vehicle('@SUM(1,1)', 'sedan');
        $customer = $this->customer('=HYPERLINK("https://example.test")', 'csv-secret@example.test');
        $booking = $this->booking($vehicle, $customer, Booking::STATUS_COMPLETED, [
            'booking_reference' => 'FG-CSV-SAFE',
            'pickup_datetime' => '2026-11-10 09:00:00',
            'return_datetime' => '2026-11-11 09:00:00',
        ]);
        $before = [
            'bookings' => Booking::count(),
            'customers' => Customer::count(),
            'vehicles' => Vehicle::count(),
            'maintenance' => VehicleUnavailability::count(),
            'booking' => $booking->only([
                'status',
                'pickup_datetime',
                'return_datetime',
                'daily_rate_snapshot',
                'estimated_total',
                'rental_duration_days',
            ]),
        ];

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']))
            ->assertOk();
        $this->assertSame(0, AuditLog::query()->where('action', 'report_exported')->count());

        $response = $this->actingAs($admin)->get(route('admin.reports.export', [
            'range' => 'custom',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
        $this->assertStringNotContainsString('csv-secret@example.test', $csv);
        $this->assertStringContainsString('Estimated Revenue (from booking snapshots)', $csv);
        $this->assertSame($before['bookings'], Booking::count());
        $this->assertSame($before['customers'], Customer::count());
        $this->assertSame($before['vehicles'], Vehicle::count());
        $this->assertSame($before['maintenance'], VehicleUnavailability::count());
        $this->assertEquals($before['booking'], $booking->fresh()->only(array_keys($before['booking'])));

        $exportLog = AuditLog::query()->where('action', 'report_exported')->firstOrFail();
        $this->assertSame($admin->id, $exportLog->user_id);
        $this->assertSame(User::class, $exportLog->subject_type);
        $this->assertSame((string) $admin->id, (string) $exportLog->subject_id);
        $this->assertSame('admin_reports', $exportLog->changes['report_type']);
        $this->assertSame('2026-11-01', $exportLog->changes['filters']['start_date']);
        $this->assertNotNull($exportLog->created_at);

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.reports.export'))
            ->assertForbidden();
        $this->assertSame(1, AuditLog::query()->where('action', 'report_exported')->count());
        $this->assertSame(Booking::count(), DB::table('bookings')->count());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function vehicle(string $id, string $category, float $dailyRate = 2500, string $status = Vehicle::STATUS_ACTIVE): Vehicle
    {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => $id,
            'brand' => 'FleetGuard',
            'model' => 'Report Test',
            'category' => $category,
            'description' => 'Report fixture.',
            'daily_rate' => $dailyRate,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => null,
            'status' => $status,
        ]);
    }

    private function customer(string $name, string $email, ?string $createdAt = null): Customer
    {
        return Customer::query()->forceCreate([
            'full_name' => $name,
            'email' => $email,
            'mobile_number' => '09171234567',
            'created_at' => $createdAt ?? '2026-11-01 08:00:00',
            'updated_at' => $createdAt ?? '2026-11-01 08:00:00',
        ]);
    }

    private function booking(Vehicle $vehicle, Customer $customer, string $status, array $overrides = []): Booking
    {
        return Booking::query()->create(array_replace([
            'booking_reference' => 'FG-'.strtoupper(str()->random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-11-10 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-11-11 09:00:00',
            'rental_duration_days' => 1,
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 2500,
            'status' => $status,
        ], $overrides));
    }
}
