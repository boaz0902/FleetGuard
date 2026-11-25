<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\LegalPolicy;
use App\Models\LegalPolicyAcceptance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 08:00:00');

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
            'image_path' => 'images/fleet/vios.webp',
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_booking_ignores_submitted_prices_duration_and_status_and_keeps_rate_snapshot(): void
    {
        $response = $this->postJson('/bookings', $this->bookingPayload([
            'daily_rate' => 1,
            'rental_duration_days' => 999,
            'estimated_total' => 1,
            'status' => Booking::STATUS_APPROVED,
            'admin_remarks' => 'approved by attacker',
        ]));

        $response->assertCreated()
            ->assertJsonPath('booking.rental_duration_days', 2)
            ->assertJsonPath('booking.daily_rate_snapshot', '2500.00')
            ->assertJsonPath('booking.estimated_total', '5000.00')
            ->assertJsonPath('booking.status', Booking::STATUS_PENDING)
            ->assertJsonMissingPath('booking.customer_id')
            ->assertJsonMissingPath('booking.admin_remarks');

        $this->assertMatchesRegularExpression('/^FG-[A-Z0-9]{8}$/', $response->json('booking.booking_reference'));

        $booking = Booking::query()->firstOrFail();
        $this->assertSame(Booking::STATUS_PENDING, $booking->status);

        Vehicle::query()->whereKey('toyota-vios')->update(['daily_rate' => 3500]);
        $this->assertSame('2500.00', $booking->fresh()->daily_rate_snapshot);
        $this->assertSame('5000.00', $booking->fresh()->estimated_total);
    }

    public function test_invalid_or_inactive_vehicle_ids_are_rejected(): void
    {
        $this->from('/')->post('/bookings', $this->bookingPayload(['vehicle_id' => 'unknown']))
            ->assertSessionHasErrors('vehicle_id');

        Vehicle::query()->whereKey('toyota-vios')->update(['status' => Vehicle::STATUS_MAINTENANCE]);
        $this->from('/')->post('/bookings', $this->bookingPayload())
            ->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_an_existing_customer_profile_cannot_be_overwritten_by_a_guest_booking(): void
    {
        $customer = Customer::query()->create([
            'full_name' => 'Original Customer',
            'email' => 'jane@example.com',
            'mobile_number' => '09171234567',
        ]);

        $this->post('/bookings', $this->bookingPayload([
            'full_name' => 'Impersonated Name',
            'mobile_number' => '09991234567',
        ]))->assertRedirect();

        $this->assertSame('Original Customer', $customer->fresh()->full_name);
        $this->assertSame('09171234567', $customer->fresh()->mobile_number);
    }

    public function test_blocked_customer_cannot_submit_a_booking_until_unblocked(): void
    {
        $customer = Customer::query()->forceCreate([
            'full_name' => 'Blocked Booking Customer',
            'email' => 'jane@example.com',
            'mobile_number' => '09171234567',
            'status' => Customer::STATUS_BLOCKED,
        ]);
        $admin = $this->userWithRole('admin');

        $this->postJson('/bookings', $this->bookingPayload([
            'full_name' => 'Changed Name',
            'email' => 'JANE@example.com',
            'mobile_number' => '09991234567',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'This booking request could not be accepted. Please contact FleetGuard support.');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('customers', 1);
        $this->assertSame('Blocked Booking Customer', $customer->fresh()->full_name);

        $this->actingAs($admin)
            ->post(route('admin.customers.unblock', $customer))
            ->assertRedirect(route('admin.customers.show', $customer));

        $this->postJson('/bookings', $this->bookingPayload([
            'full_name' => 'Changed Name',
            'email' => 'JANE@example.com',
            'mobile_number' => '09991234567',
        ]))->assertCreated();

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertSame('Blocked Booking Customer', $customer->fresh()->full_name);
        $this->assertSame(Customer::STATUS_ACTIVE, $customer->fresh()->status);
    }

    public function test_booking_submission_rejects_an_invalid_vehicle_and_non_increasing_dates(): void
    {
        $this->from('/')->post('/bookings', $this->bookingPayload([
            'return_date' => '2026-12-01',
            'return_time' => '09:00',
        ]))->assertSessionHasErrors('return_date');

        $this->from('/')->post('/bookings', $this->bookingPayload([
            'pickup_date' => now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('pickup_date');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_json_booking_validation_returns_field_errors_without_saving(): void
    {
        $this->postJson('/bookings', $this->bookingPayload([
            'mobile_number' => 'not-a-philippine-mobile',
        ]))->assertUnprocessable()->assertJsonValidationErrors('mobile_number');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_identity_photos_are_optional_private_and_visible_only_to_booking_reviewers(): void
    {
        Storage::fake('local');

        $this->from('/')->post('/bookings', $this->bookingPayload([
            'license_front' => UploadedFile::fake()->image('license-front.jpg'),
            'license_back' => UploadedFile::fake()->image('license-back.png'),
            'license_selfie' => UploadedFile::fake()->image('license-selfie.webp'),
        ]))->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertNotNull($booking->license_front_path);
        $this->assertNotNull($booking->license_back_path);
        $this->assertNotNull($booking->license_selfie_path);
        $this->assertArrayNotHasKey('license_front_path', $booking->toArray());
        Storage::disk('local')->assertExists($booking->license_front_path);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Identity documents')
            ->assertSee('Driver’s license · front');
        $this->get(route('admin.bookings.documents.show', [$booking, 'license-front']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $customer = $this->userWithRole('customer');
        $this->actingAs($customer)
            ->get(route('admin.bookings.documents.show', [$booking, 'license-front']))
            ->assertForbidden();
    }

    public function test_booking_rejects_non_image_identity_document_uploads(): void
    {
        $this->from('/')->post('/bookings', $this->bookingPayload([
            'license_front' => UploadedFile::fake()->createWithContent('license.svg', '<svg></svg>'),
        ]))->assertSessionHasErrors('license_front');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_pending_bookings_can_overlap_submissions(): void
    {
        $this->createBooking(['status' => Booking::STATUS_PENDING]);

        $this->from('/')->post('/bookings', $this->bookingPayload())
            ->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_approved_and_ongoing_bookings_block_overlapping_submissions(): void
    {
        foreach ([Booking::STATUS_APPROVED, Booking::STATUS_ONGOING] as $status) {
            LegalPolicyAcceptance::query()->delete();
            Booking::query()->delete();
            Customer::query()->delete();
            $this->createBooking(['status' => $status]);

            $this->from('/')->post('/bookings', $this->bookingPayload())
                ->assertSessionHasErrors('vehicle_id');
        }
    }

    public function test_consecutive_bookings_are_allowed_and_rejected_or_cancelled_bookings_do_not_block(): void
    {
        $this->createBooking([
            'status' => Booking::STATUS_APPROVED,
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_datetime' => '2026-12-03 09:00:00',
        ]);

        $this->post('/bookings', $this->bookingPayload([
            'pickup_date' => '2026-12-03',
            'return_date' => '2026-12-05',
        ]))->assertRedirect();

        LegalPolicyAcceptance::query()->delete();
        Booking::query()->delete();
        Customer::query()->delete();

        $this->createBooking(['status' => Booking::STATUS_REJECTED]);
        $this->createBooking([
            'status' => Booking::STATUS_CANCELLED,
            'pickup_datetime' => '2026-12-10 09:00:00',
            'return_datetime' => '2026-12-12 09:00:00',
        ]);

        $this->post('/bookings', $this->bookingPayload())->assertRedirect();
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_availability_endpoint_returns_database_vehicles_and_rates_without_customer_data(): void
    {
        $this->getJson(route('vehicles.availability', $this->availabilityQuery()))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'category',
                    'category_label',
                    'passengers',
                    'transmission',
                    'fuel',
                    'daily_rate',
                    'image_url',
                ]],
                'meta' => ['pickup_datetime', 'return_datetime', 'timezone'],
            ])
            ->assertJsonPath('data.0.id', 'toyota-vios')
            ->assertJsonPath('data.0.daily_rate', '2500.00')
            ->assertJsonPath('meta.timezone', 'Asia/Manila')
            ->assertJsonMissingPath('data.0.customer')
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_public_vehicle_schedule_lists_confirmed_bookings_and_maintenance_without_private_details(): void
    {
        $this->createBooking([
            'booking_reference' => 'PRIVATE-REFERENCE',
            'status' => Booking::STATUS_APPROVED,
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_datetime' => '2026-12-03 09:00:00',
        ]);
        $this->createBooking([
            'status' => Booking::STATUS_PENDING,
            'pickup_datetime' => '2026-12-07 09:00:00',
            'return_datetime' => '2026-12-08 09:00:00',
        ]);
        $this->createUnavailability([
            'start_datetime' => '2026-12-05 08:00:00',
            'end_datetime' => '2026-12-06 17:00:00',
            'reason' => 'Internal brake inspection',
        ]);

        $this->getJson(route('vehicles.schedule', 'toyota-vios'))
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.days_ahead', 90)
            ->assertJsonCount(2, 'data.periods')
            ->assertJsonPath('data.periods.0.type', 'booking')
            ->assertJsonPath('data.periods.0.label', 'Booked')
            ->assertJsonPath('data.periods.0.start', 'Dec 1, 2026 · 9:00 AM')
            ->assertJsonPath('data.periods.0.end', 'Dec 3, 2026 · 9:00 AM')
            ->assertJsonPath('data.periods.1.type', 'maintenance')
            ->assertJsonPath('data.periods.1.label', 'Maintenance')
            ->assertJsonMissingPath('data.periods.0.booking_reference')
            ->assertJsonMissingPath('data.periods.0.customer')
            ->assertDontSee('PRIVATE-REFERENCE')
            ->assertDontSee('Internal brake inspection');
    }

    public function test_only_approved_and_ongoing_bookings_block_availability(): void
    {
        foreach ([
            Booking::STATUS_APPROVED => false,
            Booking::STATUS_ONGOING => false,
            Booking::STATUS_PENDING => true,
            Booking::STATUS_CANCELLED => true,
            Booking::STATUS_REJECTED => true,
            Booking::STATUS_COMPLETED => true,
        ] as $status => $isAvailable) {
            LegalPolicyAcceptance::query()->delete();
            Booking::query()->delete();
            Customer::query()->delete();
            $this->createBooking(['status' => $status]);

            $response = $this->getJson(route('vehicles.availability', $this->availabilityQuery()))->assertOk();
            $this->assertSame($isAvailable, collect($response->json('data'))->contains('id', 'toyota-vios'), $status);
        }
    }

    public function test_inactive_vehicles_are_not_returned_by_availability_search(): void
    {
        Vehicle::query()->whereKey('toyota-vios')->update(['status' => Vehicle::STATUS_INACTIVE]);

        $this->getJson(route('vehicles.availability', $this->availabilityQuery()))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_maintenance_period_blocks_overlaps_but_allows_the_exclusive_end_boundary(): void
    {
        $this->createUnavailability([
            'start_datetime' => '2026-12-01 09:00:00',
            'end_datetime' => '2026-12-02 09:00:00',
        ]);

        $this->getJson(route('vehicles.availability', $this->availabilityQuery()))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson(route('vehicles.availability', $this->availabilityQuery([
            'pickup_date' => '2026-12-02',
            'pickup_time' => '09:00',
            'return_date' => '2026-12-03',
            'return_time' => '09:00',
        ])))->assertOk()->assertJsonPath('data.0.id', 'toyota-vios');
    }

    public function test_availability_search_rejects_reversed_and_non_increasing_ranges(): void
    {
        $this->getJson(route('vehicles.availability', $this->availabilityQuery([
            'return_date' => '2026-11-30',
        ])))->assertUnprocessable()->assertJsonValidationErrors('return_date');

        $this->getJson(route('vehicles.availability', $this->availabilityQuery([
            'return_date' => '2026-12-01',
            'return_time' => '09:00',
        ])))->assertUnprocessable()->assertJsonValidationErrors('return_time');
    }

    public function test_next_rental_can_begin_at_the_previous_booking_exclusive_checkout_time(): void
    {
        $this->createBooking([
            'status' => Booking::STATUS_APPROVED,
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_datetime' => '2026-12-03 09:00:00',
        ]);

        $this->getJson(route('vehicles.availability', $this->availabilityQuery([
            'pickup_date' => '2026-12-03',
            'pickup_time' => '09:00',
            'return_date' => '2026-12-04',
            'return_time' => '09:00',
        ])))->assertOk()->assertJsonPath('data.0.id', 'toyota-vios');
    }

    public function test_booking_creation_rechecks_approved_bookings_and_maintenance_inside_transaction(): void
    {
        $this->createBooking(['status' => Booking::STATUS_APPROVED]);

        $this->postJson('/bookings', $this->bookingPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        LegalPolicyAcceptance::query()->delete();
        Booking::query()->delete();
        Customer::query()->delete();
        $this->createUnavailability();

        $this->postJson('/bookings', $this->bookingPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_approval_rechecks_maintenance_and_allows_overlapping_pending_requests(): void
    {
        $manager = $this->userWithRole('manager');
        $booking = $this->createBooking(['status' => Booking::STATUS_PENDING]);
        $this->createBooking(['status' => Booking::STATUS_PENDING]);

        $this->actingAs($manager)
            ->patchJson(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertOk();

        $secondBooking = Booking::query()->where('id', '<>', $booking->id)->firstOrFail();
        $this->actingAs($manager)
            ->patchJson(route('admin.bookings.status', $secondBooking), ['status' => Booking::STATUS_APPROVED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $secondBooking->update(['status' => Booking::STATUS_PENDING]);
        Booking::query()->whereKey($booking->id)->update(['status' => Booking::STATUS_PENDING]);
        $this->createUnavailability();

        $this->actingAs($manager)
            ->patchJson(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_calendar_shows_customer_names_and_preserves_its_range_when_opening_a_reservation(): void
    {
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $booking = $this->createBooking(['status' => Booking::STATUS_APPROVED]);
        $booking->customer->update(['email' => 'private-calendar@example.com']);

        $this->actingAs($manager)
            ->get(route('admin.calendar.index', ['start_date' => '2026-12-01']))
            ->assertOk()
            ->assertSee($booking->booking_reference)
            ->assertSee(route('admin.bookings.show', ['booking' => $booking, 'calendar_start_date' => '2026-12-01']), false)
            ->assertSee($booking->customer->full_name)
            ->assertSee('width: 14.2857%;', false)
            ->assertSee('Approved')
            ->assertDontSee('private-calendar@example.com');

        $this->get(route('admin.bookings.show', ['booking' => $booking, 'calendar_start_date' => '2026-12-01']))
            ->assertOk()
            ->assertSee(route('admin.calendar.index', ['start_date' => '2026-12-01']), false)
            ->assertSee('Back to availability calendar');

        $this->actingAs($staff)->get(route('admin.calendar.index'))->assertForbidden();
        $this->app['auth']->logout();
        $this->get(route('admin.calendar.index'))->assertRedirect(route('admin.login'));
    }

    public function test_only_administrators_can_manage_maintenance_periods(): void
    {
        $manager = $this->userWithRole('manager');
        $payload = [
            'vehicle_id' => 'toyota-vios',
            'start_datetime' => '2026-12-01T09:00',
            'end_datetime' => '2026-12-02T09:00',
            'reason' => 'Scheduled service',
        ];

        $this->actingAs($manager)
            ->post(route('admin.vehicle-unavailabilities.store'), $payload)
            ->assertForbidden();

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)
            ->from(route('admin.calendar.index'))
            ->post(route('admin.vehicle-unavailabilities.store'), array_replace($payload, [
                'end_datetime' => '2026-11-30T09:00',
            ]))
            ->assertSessionHasErrors('end_datetime');
        $this->assertDatabaseCount('vehicle_unavailabilities', 0);

        $this->actingAs($admin)
            ->post(route('admin.vehicle-unavailabilities.store'), $payload)
            ->assertRedirect(route('admin.calendar.index'));

        $unavailability = VehicleUnavailability::query()->where('reason', 'Scheduled service')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle_unavailability_created',
            'subject_type' => VehicleUnavailability::class,
            'subject_id' => $unavailability->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.calendar.index', ['start_date' => '2026-12-01']))
            ->assertOk()
            ->assertSee('Schedule vehicle maintenance')
            ->assertSee('class="admin-maintenance-form"', false)
            ->assertSee('Scheduled service')
            ->assertSee('calendar-state--maintenance', false)
            ->assertSee('Remove maintenance');

        $this->actingAs($admin)
            ->delete(route('admin.vehicle-unavailabilities.destroy', $unavailability))
            ->assertRedirect(route('admin.calendar.index'));

        $this->assertDatabaseMissing('vehicle_unavailabilities', ['id' => $unavailability->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle_unavailability_removed',
            'subject_type' => VehicleUnavailability::class,
            'subject_id' => $unavailability->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_approval_rechecks_conflicts_and_audits_the_correct_status_transition(): void
    {
        $manager = $this->userWithRole('manager');
        $booking = $this->createBooking(['status' => Booking::STATUS_PENDING]);
        $conflict = $this->createBooking([
            'status' => Booking::STATUS_APPROVED,
            'pickup_datetime' => '2026-12-02 09:00:00',
            'return_datetime' => '2026-12-04 09:00:00',
        ]);

        $this->actingAs($manager)
            ->from('/admin/bookings')
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);

        $conflict->update(['status' => Booking::STATUS_REJECTED]);
        $this->actingAs($manager)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertRedirect(route('admin.bookings.index'));

        $audit = AuditLog::query()->where('subject_id', $booking->id)->latest('id')->firstOrFail();
        $this->assertSame(Booking::STATUS_PENDING, $audit->changes['from']);
        $this->assertSame(Booking::STATUS_APPROVED, $audit->changes['to']);
        $this->assertSame($manager->id, $audit->user_id);

        $rejection = $this->createBooking([
            'pickup_datetime' => '2026-12-10 09:00:00',
            'return_datetime' => '2026-12-12 09:00:00',
        ]);
        $this->actingAs($manager)
            ->patch(route('admin.bookings.status', $rejection), ['status' => Booking::STATUS_REJECTED])
            ->assertRedirect(route('admin.bookings.index'));

        $rejectionAudit = AuditLog::query()->where('subject_id', $rejection->id)->latest('id')->firstOrFail();
        $this->assertSame(Booking::STATUS_PENDING, $rejectionAudit->changes['from']);
        $this->assertSame(Booking::STATUS_REJECTED, $rejectionAudit->changes['to']);
    }

    public function test_manager_can_review_but_staff_and_customers_cannot_access_admin_endpoints(): void
    {
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('customer');

        $this->actingAs($manager)->get(route('admin.bookings.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.bookings.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->app['auth']->logout();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $booking = $this->createBooking();
        $this->actingAs($staff)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($staff)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_APPROVED])
            ->assertForbidden();
    }

    public function test_customers_can_only_view_their_own_bookings(): void
    {
        $customerUser = $this->userWithRole('customer');
        $ownCustomer = Customer::query()->create([
            'user_id' => $customerUser->id,
            'full_name' => 'Customer One',
            'email' => 'one@example.com',
            'mobile_number' => '09171234567',
        ]);
        $ownBooking = $this->createBooking(['customer_id' => $ownCustomer->id]);
        $otherBooking = $this->createBooking();

        $this->actingAs($customerUser)->get(route('bookings.show', $ownBooking))->assertOk();
        $this->get(route('bookings.show', $otherBooking))->assertForbidden();
    }

    public function test_authenticated_customers_are_linked_to_their_own_booking_records(): void
    {
        $customer = $this->userWithRole('customer');
        $customer->forceFill(['email' => 'jane@example.com'])->save();

        $this->actingAs($customer)
            ->post('/bookings', $this->bookingPayload())
            ->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($customer->id, $booking->customer->user_id);

        $this->get(route('bookings.show', $booking))->assertOk();
        $this->post('/bookings', $this->bookingPayload(['email' => 'someone-else@example.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_role_cannot_be_assigned_through_mass_assignment_and_customers_cannot_manage_audit_logs(): void
    {
        $user = User::query()->create([
            'name' => 'Attempted Admin',
            'email' => 'attempt@example.com',
            'password' => 'correct-horse-battery-staple',
            'role' => 'admin',
        ]);

        $this->assertSame('customer', $user->fresh()->role);
        $this->assertFalse(Gate::forUser($user)->allows('create', AuditLog::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', AuditLog::class));
        $this->actingAs($user)->post('/audit-logs', ['action' => 'forged'])->assertNotFound();
    }

    public function test_admin_login_regenerates_session_and_logout_invalidates_it(): void
    {
        $admin = $this->userWithRole('admin');
        $sessionIdBeforeLogin = $this->app['session.store']->getId();

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $sessionIdAfterLogin = $this->app['session.store']->getId();
        $this->assertNotSame($sessionIdBeforeLogin, $sessionIdAfterLogin);

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->assertNotSame($sessionIdAfterLogin, $this->app['session.store']->getId());
    }

    public function test_failed_admin_login_is_safe_and_login_attempts_are_throttled(): void
    {
        $manager = $this->userWithRole('manager');
        $sessionIdBeforeAttempt = $this->app['session.store']->getId();

        $this->from('/admin/login')->post(route('admin.login.submit'), [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNotSame($sessionIdBeforeAttempt, $this->app['session.store']->getId());

        RateLimiter::clear('127.0.0.1:blocked@example.com');
        RateLimiter::clear('127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login.submit'), [
                'email' => 'blocked@example.com',
                'password' => 'incorrect',
            ]);
        }

        $this->post(route('admin.login.submit'), [
            'email' => 'blocked@example.com',
            'password' => 'incorrect',
        ])->assertTooManyRequests();
    }

    public function test_booking_submissions_are_rate_limited_and_csrf_middleware_is_attached(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post('/bookings', []);
        }

        $this->post('/bookings', [])->assertTooManyRequests();

        $route = app('router')->getRoutes()->getByName('bookings.store');
        $middleware = app('router')->gatherRouteMiddleware($route);

        $this->assertTrue(collect($middleware)->contains(
            fn (string $middlewareName): bool => str_contains($middlewareName, 'VerifyCsrfToken')
                || str_contains($middlewareName, 'ValidateCsrfToken'),
        ));
    }

    public function test_database_seeder_is_idempotent_and_preserves_vehicle_status(): void
    {
        Vehicle::query()->whereKey('toyota-vios')->update(['status' => Vehicle::STATUS_MAINTENANCE]);

        $this->seed(DatabaseSeeder::class);
        $vehicleCount = Vehicle::query()->count();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($vehicleCount, Vehicle::query()->count());
        $this->assertSame(Vehicle::STATUS_MAINTENANCE, Vehicle::query()->findOrFail('toyota-vios')->status);
    }

    public function test_booking_foreign_keys_prevent_deleting_referenced_vehicles(): void
    {
        $this->createBooking();

        $this->expectException(QueryException::class);
        Vehicle::query()->whereKey('toyota-vios')->delete();
    }

    private function bookingPayload(array $overrides = []): array
    {
        $policyVersions = LegalPolicy::query()
            ->whereIn('slug', LegalPolicy::bookingAgreementSlugs())
            ->get()
            ->mapWithKeys(fn (LegalPolicy $policy): array => [$policy->slug => $policy->currentPublishedVersion?->id])
            ->all();

        return array_replace([
            'vehicle_id' => 'toyota-vios',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'mobile_number' => '09171234567',
            'pickup_location' => 'Panabo City',
            'pickup_date' => '2026-12-01',
            'pickup_time' => '09:00',
            'return_location' => 'Panabo City',
            'return_date' => '2026-12-03',
            'return_time' => '09:00',
            'customer_notes' => 'Need a reliable sedan for the weekend.',
            'agreements' => array_fill_keys(LegalPolicy::bookingAgreementSlugs(), true),
            'policy_versions' => $policyVersions,
        ], $overrides);
    }

    private function availabilityQuery(array $overrides = []): array
    {
        return array_replace([
            'pickup_date' => '2026-12-01',
            'pickup_time' => '09:00',
            'return_date' => '2026-12-03',
            'return_time' => '09:00',
        ], $overrides);
    }

    private function createUnavailability(array $overrides = []): VehicleUnavailability
    {
        return VehicleUnavailability::query()->create(array_replace([
            'vehicle_id' => 'toyota-vios',
            'start_datetime' => '2026-12-01 09:00:00',
            'end_datetime' => '2026-12-03 09:00:00',
            'reason' => 'Scheduled maintenance',
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function createBooking(array $overrides = []): Booking
    {
        $customer = Customer::query()->find($overrides['customer_id'] ?? null);

        if (! $customer) {
            $customer = Customer::query()->create([
                'full_name' => 'Booking Customer',
                'email' => 'booking-'.uniqid().'@example.com',
                'mobile_number' => '09171234567',
            ]);
        }

        return Booking::query()->create(array_replace([
            'booking_reference' => 'FG-'.strtoupper(str()->random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => 'toyota-vios',
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-12-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 2500,
            'estimated_total' => 5000,
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }
}
