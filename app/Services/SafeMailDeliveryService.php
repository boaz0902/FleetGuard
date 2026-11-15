<?php

namespace App\Services;

use App\Mail\FleetGuardMessageMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SafeMailDeliveryService
{
    private const BOOKING_EMAILS_PER_HOUR = 3;

    public function notify(User $user, Notification $notification): void
    {
        try {
            NotificationFacade::send($user, $notification);
        } catch (Throwable $exception) {
            $this->logFailure($exception, $user->email, 'account_notification');
        }
    }

    public function sendBookingNotification(Booking $booking, string $event): void
    {
        $email = '';

        try {
            $booking->loadMissing(['customer.user', 'vehicle']);
            $email = strtolower(trim((string) $booking->customer?->email));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::info('Booking email skipped because no usable recipient is available.', [
                    'event' => $event,
                    'booking_id' => $booking->id,
                ]);

                return;
            }

            $mail = $this->bookingMail($booking, $email, $event);

            DB::afterCommit(function () use ($booking, $email, $event, $mail): void {
                try {
                    $dedupeKey = "fleetguard:booking-email:{$event}:{$booking->id}";

                    if (! Cache::add($dedupeKey, true)) {
                        return;
                    }

                    $recipientKey = 'booking-email-recipient:'.hash('sha256', $email);
                    $queued = RateLimiter::attempt(
                        $recipientKey,
                        self::BOOKING_EMAILS_PER_HOUR,
                        fn (): bool => $this->queue($email, $mail),
                        3600,
                    );

                    if (! $queued) {
                        Log::info('Booking email skipped because the recipient limit was reached.', [
                            'event' => $event,
                            'booking_id' => $booking->id,
                            'recipient_domain' => $this->recipientDomain($email),
                        ]);
                    }
                } catch (Throwable $exception) {
                    $this->logFailure($exception, $email, $event, $booking->id);
                }
            });
        } catch (Throwable $exception) {
            $this->logFailure($exception, $email, $event, $booking->id);
        }
    }

    public function sendTest(string $email): bool
    {
        $mail = new FleetGuardMessageMail(
            subjectLine: 'FleetGuard test email',
            preheader: 'A test of your FleetGuard email delivery configuration.',
            heading: 'Your email setup is working',
            intro: 'This branded test message confirms that the configured mail transport can deliver email.',
            note: 'This is a test message. No booking or account action was taken.',
            eventType: 'mail_test',
        );

        try {
            Mail::to($email)->send($mail);

            return true;
        } catch (Throwable $exception) {
            $this->logFailure($exception, $email, 'mail_test');

            return false;
        }
    }

    private function queue(string $email, FleetGuardMessageMail $mail): bool
    {
        Mail::to($email)->queue($mail->afterCommit());

        return true;
    }

    private function bookingMail(Booking $booking, string $email, string $event): FleetGuardMessageMail
    {
        $customerUser = $booking->customer?->user;
        $hasVerifiedAccount = $customerUser?->isCustomer() && $customerUser->hasVerifiedEmail();
        $action = $hasVerifiedAccount
            ? [
                'label' => 'View in My Bookings',
                'url' => route('account.bookings'),
            ]
            : null;
        $callout = $hasVerifiedAccount ? null : [
            'heading' => 'Want to track or cancel this request?',
            'text' => 'Create an account with the same email address. Your request will be linked after you verify that email.',
            'action' => [
                'label' => 'Create account',
                'url' => route('register', ['email' => $email]),
            ],
        ];
        $details = [
            ['label' => 'Booking reference', 'value' => $booking->booking_reference],
            ['label' => 'Vehicle', 'value' => (string) $booking->vehicle?->name],
            [
                'label' => 'Pickup',
                'value' => $booking->pickup_datetime
                    ->copy()
                    ->timezone(config('app.timezone'))
                    ->format('M j, Y, g:i A'),
            ],
            [
                'label' => 'Return',
                'value' => $booking->return_datetime
                    ->copy()
                    ->timezone(config('app.timezone'))
                    ->format('M j, Y, g:i A'),
            ],
        ];

        if ($event === 'received') {
            $details[] = [
                'label' => 'Estimated total (from stored rate)',
                'value' => 'PHP '.number_format((float) $booking->estimated_total, 2),
            ];
        }

        [$subject, $preheader, $heading, $intro, $note] = match ($event) {
            'received' => [
                'Request received',
                'Your FleetGuard booking request is awaiting review.',
                'Request received',
                'We received your request. The details are below.',
                'This request is not confirmed until the FleetGuard team approves it.',
            ],
            'approved' => [
                'Request approved',
                'Your FleetGuard booking request has been approved.',
                'Request approved',
                $this->approvalNextSteps(),
                null,
            ],
            'rejected' => [
                'Request update',
                'There is an update about your FleetGuard booking request.',
                'Request update',
                'We are sorry, but FleetGuard could not approve this request. Contact the team using the details below if you would like to discuss other options.',
                null,
            ],
            'cancelled' => [
                'Request cancelled',
                'Your pending FleetGuard booking request has been cancelled.',
                'Request cancelled',
                'Your pending booking request has been cancelled as requested.',
                null,
            ],
            default => throw new \InvalidArgumentException('Unsupported booking email event.'),
        };

        return new FleetGuardMessageMail(
            subjectLine: $subject,
            preheader: $preheader,
            heading: $heading,
            intro: $intro,
            details: $details,
            action: $action,
            callout: $callout,
            note: $note,
            eventType: $event,
            bookingId: $booking->id,
        );
    }

    private function approvalNextSteps(): string
    {
        $hasContactDetails = collect([
            config('fleetguard.phone'),
            config('fleetguard.email'),
            config('fleetguard.address'),
        ])->contains(fn ($value): bool => is_string($value) && trim($value) !== '');

        return $hasContactDetails
            ? 'FleetGuard approved your request. Please contact the team using the details below to coordinate next steps.'
            : 'FleetGuard approved your request. The team will share any remaining next-step details directly; contact information is not currently listed.';
    }

    private function logFailure(
        Throwable $exception,
        string $email,
        string $event,
        ?int $bookingId = null,
    ): void {
        Log::warning('Email delivery failed.', [
            'exception' => $exception::class,
            'event' => $event,
            'booking_id' => $bookingId,
            'recipient_domain' => $this->recipientDomain($email),
        ]);
    }

    private function recipientDomain(string $email): string
    {
        $separator = strrpos($email, '@');

        return $separator === false ? 'unknown' : substr($email, $separator + 1);
    }
}