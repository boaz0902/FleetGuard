<?php

namespace Tests\Feature;

use App\Mail\FleetGuardMessageMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ResetCustomerPassword;
use App\Notifications\VerifyCustomerEmail;
use App\Services\BookingStatusTransitionService;
use App\Services\SafeMailDeliveryService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class BookingEmailDeliveryTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->vehicle();
    }

    public function test_guest_request_email_is_queued_once_with_estimate_and_account_prompt(): void
    {
        Mail::fake();

        $response = $this->postJson(route('bookings.store'), $this->bookingPayload());

        $response->assertCreated();
        $booking = Booking::query()->firstOrFail();
        $this->assertQueuedMail('guest@example.test', 'Request received');

        $mail = Mail::queued(FleetGuardMessageMail::class)->first();
        $this->assertNotNull($mail);
        $html = $mail->render();
        $text = view('emails.text.message', $mail->buildViewData())->render();

        $this->assertStringContainsString('This request is not confirmed until the FleetGuard team approves it.', $html);
        $this->assertStringContainsString('Estimated total (from stored rate)', $html);
        $this->assertStringContainsString($booking->booking_reference, $html);
        $this->assertStringContainsString('Mail Test Vehicle', $html);
        $this->assertStringContainsString('Dec 10, 2026', $html);
        $this->assertStringContainsString('10:00 AM', $html);
        $this->assertStringContainsString('PHP 1,800.00', $html);
        $this->assertStringContainsString('Booking reference', $html);
        $this->assertStringContainsString('Want to track or cancel this request?', $html);
        $this->assertStringContainsString('Your request will be linked after you verify that email.', $text);
        $this->assertStringContainsString('Create account:', $text);
        $this->assertStringNotContainsString('INTERNAL ADMIN REMARK', $html);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Want to track or cancel this request?', false)
            ->assertSee('data-confirmation-guest-prompt', false)
            ->assertSee('data-customer-account-state="guest"', false);

        $this->get(route('register', ['email' => 'guest@example.test']))
            ->assertOk()
            ->assertSee('value="guest@example.test"', false)
            ->assertSee('readonly', false)
            ->assertSee('will link to this account after you verify', false);
    }

    public function test_verified_customer_booking_email_links_to_my_bookings(): void
    {
        Mail::fake();
        $user = $this->user('tracked@example.test', 'customer', true);
        $customer = $this->customer($user->email, $user);
        $booking = $this->booking($customer);

        app(SafeMailDeliveryService::class)->sendBookingNotification($booking, 'received');
        app(SafeMailDeliveryService::class)->sendBookingNotification($booking, 'received');

        Mail::assertQueued(FleetGuardMessageMail::class, 1);
        $mail = Mail::queued(FleetGuardMessageMail::class)->first();
        $this->assertNotNull($mail);
        $this->assertSame('Request received', $mail->subjectLine);
        $this->assertSame('View in My Bookings', $mail->action['label']);
        $this->assertSame(route('account.bookings'), $mail->action['url']);
        $this->assertNull($mail->callout);
    }

    public function test_approval_and_rejection_emails_follow_only_successful_pending_transitions(): void
    {
        Mail::fake();
        $admin = $this->user('admin@example.test', 'admin', true);
        $customer = $this->customer('decision@example.test');
        $approved = $this->booking($customer);
        $rejected = $this->booking($customer);

        app(BookingStatusTransitionService::class)->transition(
            $approved,
            Booking::STATUS_APPROVED,
            $admin,
        );
        app(BookingStatusTransitionService::class)->transition(
            $rejected,
            Booking::STATUS_REJECTED,
            $admin,
            'INTERNAL ADMIN REMARK',
        );

        Mail::assertQueued(FleetGuardMessageMail::class, 2);
        $messages = Mail::queued(FleetGuardMessageMail::class);
        $approvedMail = $messages->first(fn (FleetGuardMessageMail $mail): bool => $mail->subjectLine === 'Request approved');
        $rejectedMail = $messages->first(fn (FleetGuardMessageMail $mail): bool => $mail->subjectLine === 'Request update');

        $this->assertNotNull($approvedMail);
        $this->assertStringContainsString('contact information is not currently listed', $approvedMail->intro);
        $this->assertNotNull($rejectedMail);
        $this->assertStringNotContainsString('INTERNAL ADMIN REMARK', $rejectedMail->render());

        try {
            app(BookingStatusTransitionService::class)->transition(
                $rejected,
                Booking::STATUS_APPROVED,
                $admin,
            );
            $this->fail('A rejected booking cannot be approved.');
        } catch (ValidationException) {
            Mail::assertQueued(FleetGuardMessageMail::class, 2);
        }
    }

    public function test_customer_cancellation_sends_one_confirmation_but_checkin_sends_none(): void
    {
        Mail::fake();
        $user = $this->user('cancel@example.test', 'customer', true);
        $customer = $this->customer($user->email, $user);
        $pending = $this->booking($customer);

        app(BookingStatusTransitionService::class)->transition(
            $pending,
            Booking::STATUS_CANCELLED,
            $user,
            '',
            'booking_cancelled_by_customer',
            ['customer_id' => $customer->id, 'booking_id' => $pending->id],
        );

        $ongoing = $this->booking($customer, ['status' => Booking::STATUS_ONGOING]);
        app(BookingStatusTransitionService::class)->transition(
            $ongoing,
            Booking::STATUS_COMPLETED,
            $this->user('admin-checkin@example.test', 'admin', true),
        );

        Mail::assertQueued(FleetGuardMessageMail::class, 1);
        $mail = Mail::queued(FleetGuardMessageMail::class)->first();
        $this->assertNotNull($mail);
        $this->assertSame('Request cancelled', $mail->subjectLine);
        $this->assertSame(Booking::STATUS_COMPLETED, $ongoing->fresh()->status);
    }

    public function test_rolled_back_transition_does_not_queue_email(): void
    {
        Mail::fake();
        $booking = $this->booking($this->customer('rollback@example.test'));
        $admin = $this->user('rollback-admin@example.test', 'admin', true);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $admin): void {
                app(BookingStatusTransitionService::class)->transition(
                    $booking,
                    Booking::STATUS_APPROVED,
                    $admin,
                );

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        Mail::assertNothingQueued();
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_delivery_failure_does_not_break_submission_or_approval(): void
    {
        Mail::shouldReceive('to')
            ->times(4)
            ->andThrow(new RuntimeException('smtp password and reset URL must not be logged'));

        $this->postJson(route('bookings.store'), $this->bookingPayload())
            ->assertCreated();

        $admin = $this->user('failure-admin@example.test', 'admin', true);
        $approved = $this->booking($this->customer('approval-failure@example.test'), [
            'pickup_datetime' => '2026-12-12 10:00:00',
            'return_datetime' => '2026-12-13 10:00:00',
        ]);
        app(BookingStatusTransitionService::class)->transition(
            $approved,
            Booking::STATUS_APPROVED,
            $admin,
        );
        $rejected = $this->booking($this->customer('rejection-failure@example.test'));
        app(BookingStatusTransitionService::class)->transition(
            $rejected,
            Booking::STATUS_REJECTED,
            $admin,
        );
        $cancelUser = $this->user('cancellation-failure@example.test', 'customer', true);
        $cancelCustomer = $this->customer($cancelUser->email, $cancelUser);
        $cancelled = $this->booking($cancelCustomer);
        app(BookingStatusTransitionService::class)->transition(
            $cancelled,
            Booking::STATUS_CANCELLED,
            $cancelUser,
            '',
            'booking_cancelled_by_customer',
            ['customer_id' => $cancelCustomer->id, 'booking_id' => $cancelled->id],
        );

        $this->assertSame(Booking::STATUS_APPROVED, $approved->fresh()->status);
        $this->assertSame(Booking::STATUS_REJECTED, $rejected->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->fresh()->status);
    }

    public function test_blocked_customer_status_is_never_included_in_booking_emails(): void
    {
        Mail::fake();
        $customer = $this->customer('privacy@example.test');
        $customer->forceFill(['status' => Customer::STATUS_BLOCKED])->save();
        $booking = $this->booking($customer);

        app(SafeMailDeliveryService::class)->sendBookingNotification($booking, 'received');

        $mail = Mail::queued(FleetGuardMessageMail::class)->first();
        $this->assertNotNull($mail);
        $this->assertStringNotContainsString('blocked', strtolower($mail->render()));
    }

    public function test_booking_email_recipient_cap_limits_queued_messages_to_three_per_hour(): void
    {
        Mail::fake();
        $email = 'limited@example.test';
        RateLimiter::clear('booking-email-recipient:'.hash('sha256', $email));
        $customer = $this->customer($email);

        for ($index = 0; $index < 4; $index++) {
            app(SafeMailDeliveryService::class)->sendBookingNotification(
                $this->booking($customer),
                'received',
            );
        }

        Mail::assertQueued(FleetGuardMessageMail::class, 3);
    }

    public function test_verification_and_reset_notifications_keep_their_secure_urls_and_branded_templates(): void
    {
        $user = $this->user('account-mail@example.test', 'customer', false);
        $verificationMail = (new VerifyCustomerEmail)->toMail($user);
        $verificationHtml = $verificationMail->render();
        $verificationUrl = $verificationMail->action['url'];

        $this->assertStringContainsString('Verify your email', $verificationHtml);
        $this->assertStringContainsString('60 minutes', $verificationHtml);
        $this->assertStringContainsString('signature=', $verificationUrl);
        $this->assertTrue(URL::hasValidSignature(request()->create($verificationUrl)));

        $resetMail = (new ResetCustomerPassword('sensitive-token'))->toMail($user);
        $resetHtml = $resetMail->render();

        $this->assertStringContainsString('Reset your password', $resetHtml);
        $this->assertStringContainsString('If you did not request this, ignore this email', $resetHtml);
        $this->assertStringContainsString('sensitive-token', $resetMail->action['url']);
    }

    public function test_each_account_and_booking_email_renders_html_and_plain_text(): void
    {
        Mail::fake();
        $mail = app(SafeMailDeliveryService::class);

        foreach (['received', 'approved', 'rejected', 'cancelled'] as $index => $event) {
            $customer = $this->customer("render-{$index}@example.test");
            $mail->sendBookingNotification($this->booking($customer), $event);
        }

        $user = $this->user('render-account@example.test', 'customer', false);
        $mailables = Mail::queued(FleetGuardMessageMail::class)->values();
        $mailables->push((new VerifyCustomerEmail)->toMail($user));
        $mailables->push((new ResetCustomerPassword('render-token'))->toMail($user));

        $this->assertCount(6, $mailables);

        foreach ($mailables as $mailable) {
            $html = $mailable->render();
            $text = view('emails.text.message', $mailable->buildViewData())->render();

            $this->assertStringContainsString('<html lang="en">', $html);
            $this->assertStringContainsString('@media only screen', $html);
            $this->assertStringContainsString($mailable->preheader, $html);
            $this->assertStringContainsString('This is an automated message.', $html);
            $this->assertStringContainsString($mailable->heading, $text);
            $this->assertStringContainsString($mailable->preheader, $text);
        }
    }

    public function test_mail_test_command_requires_an_address_and_sends_a_branded_message(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $this->artisan('fleetguard:mail-test', ['email' => 'not-an-email'])
            ->assertExitCode(2);
        $this->artisan('fleetguard:mail-test', ['email' => 'sandbox@example.test'])
            ->expectsOutput('Test email sent successfully.')
            ->assertSuccessful();

        Mail::assertSent(FleetGuardMessageMail::class, fn (FleetGuardMessageMail $mail): bool => $mail->hasTo('sandbox@example.test')
            && $mail->subjectLine === 'FleetGuard test email');
    }

    public function test_mail_test_warns_when_an_async_queue_has_no_worker(): void
    {
        Mail::fake();
        Process::fake(['*' => Process::result('0 ')])->preventStrayProcesses();
        config(['queue.default' => 'database']);

        $this->artisan('fleetguard:mail-test', ['email' => 'sandbox@example.test'])
            ->expectsOutputToContain('A queue worker must be running for queued email delivery')
            ->expectsOutputToContain('No queue worker process was detected.')
            ->expectsOutput('Test email sent successfully.')
            ->assertSuccessful();

        Mail::assertSent(FleetGuardMessageMail::class, fn (FleetGuardMessageMail $mail): bool => $mail->hasTo('sandbox@example.test'));
    }

    public function test_registration_verification_email_is_processed_by_the_queue_worker(): void
    {
        config([
            'app.env' => 'testing',
            'mail.default' => 'array',
            'queue.default' => 'database',
        ]);

        $this->post(route('register.submit'), [
            'name' => 'Queue E2E Test Customer',
            'email' => 'queue-e2e@example.test',
            'password' => 'FleetGuard9!',
            'password_confirmation' => 'FleetGuard9!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work --stop-when-empty --tries=1')
            ->assertSuccessful();

        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame(
            'queue-e2e@example.test',
            $messages->first()->getEnvelope()->getRecipients()[0]->getAddress(),
        );
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_mail_test_command_requires_an_explicit_address(): void
    {
        $this->expectException(RuntimeException::class);

        $this->artisan('fleetguard:mail-test');
    }

    public function test_mail_test_command_reports_a_sanitized_delivery_failure(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('sandbox@example.test')
            ->andThrow(new RuntimeException('SMTP password and private token'));

        $this->artisan('fleetguard:mail-test', ['email' => 'sandbox@example.test'])
            ->expectsOutput('Email delivery failed. Check the mail configuration and sanitized application log.')
            ->assertExitCode(1);
    }

    public function test_queued_transport_failure_logs_only_sanitized_context(): void
    {
        $mailer = \Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP password and private URL'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Email delivery failed.', [
                'exception' => RuntimeException::class,
                'event' => 'approved',
                'booking_id' => 42,
                'recipient_domain' => 'example.test',
            ]);

        $message = new FleetGuardMessageMail(
            subjectLine: 'Request approved',
            preheader: 'A request was approved.',
            heading: 'Request approved',
            intro: 'The request has been approved.',
            eventType: 'approved',
            bookingId: 42,
        );
        $message->to('customer@example.test');

        $this->assertNull($message->send($mailer));
    }

    private function assertQueuedMail(string $recipient, string $subject): void
    {
        Mail::assertQueued(
            FleetGuardMessageMail::class,
            fn (FleetGuardMessageMail $mail): bool => $mail->hasTo($recipient)
                && $mail->subjectLine === $subject
                && $mail->afterCommit === true,
        );
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'id' => 'mail-test-vehicle',
            'name' => 'Mail Test Vehicle',
            'brand' => 'FleetGuard',
            'model' => 'Test',
            'category' => 'sedan',
            'description' => 'Test vehicle.',
            'daily_rate' => 1800,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => null,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    private function user(string $email, string $role, bool $verified): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->forceFill([
            'role' => $role,
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        return $user->fresh();
    }

    private function customer(string $email, ?User $user = null): Customer
    {
        return Customer::query()->forceCreate([
            'user_id' => $user?->id,
            'full_name' => 'Test Customer',
            'email' => $email,
            'mobile_number' => '09171234567',
        ]);
    }

    private function booking(Customer $customer, array $overrides = []): Booking
    {
        return Booking::query()->create(array_replace([
            'booking_reference' => 'FG-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'vehicle_id' => 'mail-test-vehicle',
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-12-10 10:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-12-11 10:00:00',
            'rental_duration_days' => 1,
            'daily_rate_snapshot' => 1800,
            'estimated_total' => 1800,
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }

    private function bookingPayload(): array
    {
        return [
            'vehicle_id' => 'mail-test-vehicle',
            'full_name' => 'Guest Customer',
            'email' => 'guest@example.test',
            'mobile_number' => '09171234567',
            'pickup_location' => 'Panabo City',
            'pickup_date' => '2026-12-10',
            'pickup_time' => '10:00',
            'return_location' => 'Panabo City',
            'return_date' => '2026-12-11',
            'return_time' => '10:00',
        ];
    }
}
