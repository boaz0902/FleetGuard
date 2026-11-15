<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
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

    public function test_registration_creates_a_customer_and_sends_a_verification_notification(): void
    {
        Notification::fake();

        $response = $this->post(route('register.submit'), [
            'name' => 'New Customer',
            'email' => '  NEW.CUSTOMER@example.com ',
            'password' => 'FleetGuard9!',
            'password_confirmation' => 'FleetGuard9!',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->firstOrFail();

        $this->assertSame('new.customer@example.com', $user->email);
        $this->assertSame('customer', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('FleetGuard9!', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'customer_account_registered',
        ]);
        $this->assertSame(
            ['user_id' => $user->id, 'role' => 'customer'],
            AuditLog::query()->where('action', 'customer_account_registered')->firstOrFail()->changes,
        );
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_registration_links_one_matching_guest_record_without_overwriting_its_profile(): void
    {
        Notification::fake();
        $customer = Customer::query()->create([
            'full_name' => 'Original Guest Name',
            'email' => 'legacy@example.com',
            'mobile_number' => '09171234567',
        ]);

        $this->post(route('register.submit'), [
            'name' => 'Account Holder',
            'email' => 'legacy@example.com',
            'password' => 'FleetGuard9!',
            'password_confirmation' => 'FleetGuard9!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->firstOrFail();
        $this->assertNull($customer->fresh()->user_id);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(route('account'));

        $this->assertSame($user->id, $customer->fresh()->user_id);
        $this->assertSame('Original Guest Name', $customer->fresh()->full_name);
        $this->assertSame('09171234567', $customer->fresh()->mobile_number);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'customer_account_linked',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_ambiguous_normalized_guest_records_are_not_linked(): void
    {
        Notification::fake();
        $first = Customer::query()->create([
            'full_name' => 'First Guest',
            'email' => 'duplicate@example.com',
            'mobile_number' => '09171234567',
        ]);
        $second = Customer::query()->create([
            'full_name' => 'Second Guest',
            'email' => 'DUPLICATE@example.com',
            'mobile_number' => '09991234567',
        ]);

        $this->post(route('register.submit'), [
            'name' => 'Duplicate Account',
            'email' => 'duplicate@example.com',
            'password' => 'FleetGuard9!',
            'password_confirmation' => 'FleetGuard9!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->firstOrFail();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(route('account'));

        $this->assertNull($first->fresh()->user_id);
        $this->assertNull($second->fresh()->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'customer_link_conflict',
            'subject_id' => $first->id,
        ]);
    }

    public function test_normalized_duplicate_registration_is_rejected(): void
    {
        $this->customerAccount('existing@example.com');

        $this->from(route('register'))->post(route('register.submit'), [
            'name' => 'Duplicate',
            'email' => ' EXISTING@example.com ',
            'password' => 'FleetGuard9!',
            'password_confirmation' => 'FleetGuard9!',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_public_login_accepts_only_customer_accounts_and_prefills_the_booking_email(): void
    {
        $customer = $this->customerAccount('customer@example.com');

        $this->post(route('login.submit'), [
            'email' => ' CUSTOMER@example.com ',
            'password' => 'password',
        ])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($customer);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('value="customer@example.com"', false)
            ->assertSee('readonly', false)
            ->assertSee('My account')
            ->assertSee('Sign out');

        auth()->logout();

        foreach (['admin', 'manager', 'staff'] as $role) {
            $user = $this->customerAccount($role.'@example.com', $role);

            $this->from(route('login'))->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_customer_logout_invalidates_the_session(): void
    {
        $user = $this->customerAccount('logout@example.com');
        $this->actingAs($user);
        $sessionId = $this->app['session.store']->getId();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotSame($sessionId, $this->app['session.store']->getId());
    }

    public function test_customer_account_shows_verification_state_without_booking_history(): void
    {
        $user = $this->customerAccount('account@example.com');

        $this->actingAs($user)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Verify your email address')
            ->assertSee('My account')
            ->assertDontSee('My Bookings');
    }

    public function test_non_customer_roles_cannot_open_customer_account_routes(): void
    {
        foreach (['admin', 'manager', 'staff'] as $role) {
            $user = $this->customerAccount($role.'@example.com', $role);

            $this->actingAs($user)->get(route('account'))->assertForbidden();
        }
    }

    public function test_customer_password_reset_sends_a_notification_and_resets_the_password(): void
    {
        Notification::fake();
        $user = $this->customerAccount('reset@example.com');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Changed9!',
            'password_confirmation' => 'Changed9!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Changed9!', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_password_reset_does_not_create_tokens_for_administrator_accounts(): void
    {
        Notification::fake();
        $admin = $this->customerAccount('admin@example.com', 'admin');

        $this->post(route('password.email'), ['email' => $admin->email])
            ->assertSessionHas('status');

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_unverified_customer_booking_does_not_link_a_guest_record_or_grant_booking_access(): void
    {
        $user = $this->customerAccount('unverified@example.com');
        $guest = Customer::query()->create([
            'full_name' => 'Guest Profile',
            'email' => $user->email,
            'mobile_number' => '09171234567',
        ]);

        $this->actingAs($user)->postJson('/bookings', $this->bookingPayload([
            'email' => $user->email,
        ]))->assertCreated();

        $booking = Booking::query()->firstOrFail();
        $this->assertNull($guest->fresh()->user_id);
        $this->get(route('bookings.show', $booking))->assertForbidden();
    }

    public function test_verified_customer_booking_links_the_matching_guest_record(): void
    {
        $user = $this->customerAccount('verified@example.com', 'customer', true);
        $guest = Customer::query()->create([
            'full_name' => 'Guest Profile',
            'email' => $user->email,
            'mobile_number' => '09171234567',
        ]);

        $this->actingAs($user)->postJson('/bookings', $this->bookingPayload([
            'email' => $user->email,
        ]))->assertCreated();

        $this->assertSame($user->id, $guest->fresh()->user_id);
        $booking = Booking::query()->firstOrFail();
        $this->get(route('bookings.show', $booking))->assertOk();
    }

    public function test_booking_link_conflict_rolls_back_the_booking_and_records_a_conflict_audit(): void
    {
        $otherAccount = $this->customerAccount('other@example.com', 'customer', true);
        $user = $this->customerAccount('shared@example.com', 'customer', true);
        $linked = Customer::query()->create([
            'user_id' => $otherAccount->id,
            'full_name' => 'Other Account',
            'email' => $user->email,
            'mobile_number' => '09171234567',
        ]);

        $this->actingAs($user)
            ->from(route('home'))
            ->post('/bookings', $this->bookingPayload(['email' => $user->email]))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame($otherAccount->id, $linked->fresh()->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'customer_link_conflict',
            'subject_id' => $linked->id,
        ]);
    }

    public function test_guest_booking_keeps_existing_linked_customer_behavior_and_profile_values(): void
    {
        $account = $this->customerAccount('linked@example.com', 'customer', true);
        $customer = Customer::query()->create([
            'user_id' => $account->id,
            'full_name' => 'Linked Account',
            'email' => $account->email,
            'mobile_number' => '09171234567',
        ]);

        $this->postJson('/bookings', $this->bookingPayload([
            'email' => $customer->email,
            'full_name' => 'Guest Submitted Name',
            'mobile_number' => '09991234567',
        ]))->assertCreated();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($account->id, $customer->fresh()->user_id);
        $this->assertSame('Linked Account', $customer->fresh()->full_name);
        $this->assertSame('09171234567', $customer->fresh()->mobile_number);
    }

    public function test_customer_registration_is_rate_limited(): void
    {
        RateLimiter::clear('127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('register.submit'), []);
        }

        $this->post(route('register.submit'), [])->assertTooManyRequests();
        RateLimiter::clear('127.0.0.1');
    }

    public function test_public_customer_login_uses_the_login_rate_limit(): void
    {
        RateLimiter::clear('127.0.0.1:blocked@example.com');
        RateLimiter::clear('127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.submit'), [
                'email' => 'blocked@example.com',
                'password' => 'incorrect',
            ]);
        }

        $this->post(route('login.submit'), [
            'email' => 'blocked@example.com',
            'password' => 'incorrect',
        ])->assertTooManyRequests();

        RateLimiter::clear('127.0.0.1:blocked@example.com');
        RateLimiter::clear('127.0.0.1');
    }

    private function customerAccount(string $email, string $role = 'customer', bool $verified = false): User
    {
        $user = User::query()->create([
            'name' => 'Test Customer',
            'email' => $email,
            'password' => 'password',
        ]);

        $user->forceFill([
            'role' => $role,
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        return $user;
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => 'toyota-vios',
            'full_name' => 'Booking Customer',
            'email' => 'booking@example.com',
            'mobile_number' => '09171234567',
            'pickup_location' => 'Panabo City Center (demo)',
            'pickup_date' => '2026-12-01',
            'pickup_time' => '10:00',
            'return_location' => 'Panabo City Center (demo)',
            'return_date' => '2026-12-02',
            'return_time' => '10:00',
            'customer_notes' => 'Test booking',
        ], $overrides);
    }
}
