<?php

namespace Tests\Feature;

use App\Mail\FleetGuardMessageMail;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\SafeMailDeliveryService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_profile_is_available_to_customers_but_not_staff_or_guests(): void
    {
        $this->get(route('account.profile'))->assertRedirect(route('login'));

        foreach (['admin', 'manager'] as $role) {
            $user = $this->customer($role.'@example.test', $role, true);
            $this->actingAs($user)->get(route('account.profile'))->assertForbidden();
        }

        $unverified = $this->customer('unverified@example.test', 'customer', false);
        $this->actingAs($unverified)->get(route('account.profile'))
            ->assertOk()
            ->assertSee('Profile &amp; Security')
            ->assertSee('Verify your email address')
            ->assertSee('email is your sign-in and booking identity');

        $verified = $this->customer('verified@example.test', 'customer', true);
        $this->actingAs($verified)->get(route('account.profile'))->assertOk();
    }

    public function test_profile_updates_name_and_phone_and_syncs_only_the_verified_customer_record(): void
    {
        $user = $this->customer('profile@example.test', 'customer', true, '09171111111');
        $customer = $this->linkedCustomer($user, '09171111111');
        $booking = $this->booking($customer);
        $bookingBefore = $booking->getAttributes();
        ksort($bookingBefore);

        $this->actingAs($user)
            ->put(route('account.profile.update'), [
                'name' => '  <b>Updated Customer</b>  ',
                'phone' => '+639123456789',
                'email' => 'changed@example.test',
                'role' => 'admin',
                'email_verified_at' => null,
                'status' => 'blocked',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'Your profile has been updated.');

        $user->refresh();
        $customer->refresh();
        $booking->refresh();
        $bookingAfter = $booking->getAttributes();
        ksort($bookingAfter);

        $this->assertSame('Updated Customer', $user->name);
        $this->assertSame('+639123456789', $user->phone);
        $this->assertSame('profile@example.test', $user->email);
        $this->assertSame('customer', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('+639123456789', $customer->mobile_number);
        $this->assertSame($bookingBefore, array_intersect_key($bookingAfter, $bookingBefore));

        $audit = AuditLog::query()->where('action', 'customer_profile_updated')->firstOrFail();
        $this->assertSame([
            'user_id' => $user->id,
            'changed_fields' => ['name', 'phone'],
        ], $audit->changes);
        $this->assertStringNotContainsString('Updated Customer', json_encode($audit->changes));
        $this->assertStringNotContainsString('+639123456789', json_encode($audit->changes));
    }

    public function test_unverified_or_unlinked_profiles_do_not_change_customer_record_phone(): void
    {
        $unverified = $this->customer('unverified-linked@example.test', 'customer', false, '09171111111');
        $unverifiedCustomer = $this->linkedCustomer($unverified, '09171111111');
        $unlinked = $this->customer('unlinked@example.test', 'customer', true, '09171111111');

        $this->actingAs($unverified)->put(route('account.profile.update'), [
            'name' => $unverified->name,
            'phone' => '09172222222',
        ])->assertRedirect(route('account.profile'));

        $this->actingAs($unlinked)->put(route('account.profile.update'), [
            'name' => $unlinked->name,
            'phone' => '09173333333',
        ])->assertRedirect(route('account.profile'));

        $this->assertSame('09171111111', $unverifiedCustomer->fresh()->mobile_number);
        $this->assertSame('09172222222', $unverified->fresh()->phone);
        $this->assertSame('09173333333', $unlinked->fresh()->phone);
    }

    public function test_no_change_is_friendly_and_does_not_create_an_audit_entry(): void
    {
        $user = $this->customer('unchanged@example.test', 'customer', true, '09171234567');

        $this->actingAs($user)
            ->put(route('account.profile.update'), [
                'name' => $user->name,
                'phone' => $user->phone,
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'Nothing to update; your profile is already current.');

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'customer_profile_updated',
            'user_id' => $user->id,
        ]);
    }

    public function test_profile_phone_validation_rejects_non_booking_phone_formats(): void
    {
        $user = $this->customer('bad-phone@example.test', 'customer', true);

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->put(route('account.profile.update'), [
                'name' => 'Valid Name',
                'phone' => '+63 912 345 6789',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHasErrors('phone');
    }

    public function test_password_change_invalidates_reset_tokens_and_sends_one_branded_notification(): void
    {
        Mail::fake();
        $user = $this->customer('password@example.test', 'customer', true);
        $token = Password::broker()->getRepository()->create($user);
        $this->assertNotEmpty($token);
        $this->actingAs($user)->get(route('account.profile'))->assertOk();
        $otherSessionPasswordHash = $this->app['session.store']->get('password_hash_web');
        $sessionId = $this->app['session.store']->getId();

        $this->actingAs($user)
            ->put(route('account.profile.password'), [
                'current_password' => 'password',
                'password' => 'A much longer New Password 9!',
                'password_confirmation' => 'A much longer New Password 9!',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'Your password has been changed.');

        $user->refresh();
        $this->assertNotSame($sessionId, $this->app['session.store']->getId());
        $this->assertTrue(Hash::check('A much longer New Password 9!', $user->password));
        $this->assertSame(0, \DB::table('password_reset_tokens')->count());

        $audit = AuditLog::query()->where('action', 'customer_password_changed')->firstOrFail();
        $this->assertSame(['user_id' => $user->id], $audit->changes);
        $this->assertStringNotContainsString('Password', json_encode($audit->changes));
        $this->assertStringNotContainsString('New Password', json_encode($audit->changes));

        Mail::assertSentCount(1);
        Mail::assertSent(FleetGuardMessageMail::class, function (FleetGuardMessageMail $mail): bool {
            $rendered = $mail->render();

            return $mail->subjectLine === 'Your password was changed'
                && $mail->action['url'] === route('password.request')
                && str_contains($rendered, 'If this was not you')
                && ! str_contains($rendered, 'A much longer New Password 9!');
        });

        app(SafeMailDeliveryService::class)->sendPasswordChangedNotification($user, $audit->id);
        Mail::assertSentCount(1);

        $this->withSession(['password_hash_web' => $otherSessionPasswordHash])
            ->actingAs($user->fresh())
            ->get(route('account'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_notification_failure_is_logged_without_blocking_password_change(): void
    {
        $user = $this->customer('password-mail-failure@example.test', 'customer', true);
        Log::spy();
        Mail::shouldReceive('to')->once()->with($user->email)->andThrow(new \RuntimeException('Transport failure.'));

        $this->actingAs($user)
            ->put(route('account.profile.password'), [
                'current_password' => 'password',
                'password' => 'A secure new password 9!',
                'password_confirmation' => 'A secure new password 9!',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'Your password has been changed.');

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Email delivery failed.'
                && $context['event'] === 'customer_password_changed'
                && ! array_key_exists('password', $context)
                && ! array_key_exists('token', $context),
        );
    }

    public function test_audit_viewer_labels_customer_authentication_actions(): void
    {
        $user = $this->customer('audit@example.test', 'customer', true);
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'customer_profile_updated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'changes' => ['user_id' => $user->id, 'changed_fields' => ['name', 'phone']],
        ]);
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'customer_password_changed',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'changes' => ['user_id' => $user->id],
        ]);
        $admin = $this->customer('audit-admin@example.test', 'admin', true);

        $this->actingAs($admin)->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Customer profile updated')
            ->assertSee('Customer password changed')
            ->assertSee('Auth')
            ->assertSee('Updated profile fields: Name, Phone.');
    }

    public function test_password_change_rejects_wrong_current_weak_and_reused_passwords(): void
    {
        $user = $this->customer('password-validation@example.test', 'customer', true);
        $this->actingAs($user);

        foreach ([
            ['wrong-current-password', 'A secure new password 9!'],
            ['password', 'short'],
            ['password', 'password'],
        ] as [$current, $new]) {
            $this->from(route('account.profile'))
                ->put(route('account.profile.password'), [
                    'current_password' => $current,
                    'password' => $new,
                    'password_confirmation' => $new,
                ])
                ->assertRedirect(route('account.profile'))
                ->assertSessionHasErrors();
        }
    }

    public function test_password_route_is_throttled(): void
    {
        $user = $this->customer('password-throttle@example.test', 'customer', true);
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->put(route('account.profile.password'), [
                'current_password' => 'incorrect',
                'password' => 'A secure new password 9!',
                'password_confirmation' => 'A secure new password 9!',
            ])->assertSessionHasErrors('current_password');
        }

        $this->put(route('account.profile.password'), [
            'current_password' => 'incorrect',
            'password' => 'A secure new password 9!',
            'password_confirmation' => 'A secure new password 9!',
        ])->assertTooManyRequests();
    }

    private function customer(string $email, string $role, bool $verified, ?string $phone = null): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->forceFill([
            'role' => $role,
            'email_verified_at' => $verified ? now() : null,
            'phone' => $phone,
        ])->save();

        return $user->fresh();
    }

    private function linkedCustomer(User $user, string $phone): Customer
    {
        return Customer::query()->create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'mobile_number' => $phone,
        ]);
    }

    private function booking(Customer $customer): Booking
    {
        Vehicle::query()->create([
            'id' => 'profile-booking-car',
            'name' => 'Profile Test Car',
            'brand' => 'FleetGuard',
            'model' => 'Test',
            'category' => 'sedan',
            'daily_rate' => 1800,
            'passenger_capacity' => 5,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        return Booking::query()->create([
            'booking_reference' => 'FG-PROFILE-0001',
            'customer_id' => $customer->id,
            'vehicle_id' => 'profile-booking-car',
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-12-01 09:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-12-03 09:00:00',
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 1800,
            'estimated_total' => 3600,
            'status' => Booking::STATUS_PENDING,
        ]);
    }
}
