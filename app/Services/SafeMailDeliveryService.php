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
            if (config('app.env') === 'local' || config('queue.default') === 'sync') {
                NotificationFacade::sendNow($user, $notification);

                return;
            }

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
                    $delivered = RateLimiter::attempt(
                        $recipientKey,
                        self::BOOKING_EMAILS_PER_HOUR,
                        fn (): bool => $this->deliverBookingMail($email, $mail),
                        3600,
                    );

                    if (! $delivered) {
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

    public function sendAdminBookingNotification(Booking $booking): void
    {
        try {
            $booking->loadMissing(['customer', 'vehicle']);
            $recipients = User::query()
                ->whereIn('role', ['admin', 'manager'])
                ->pluck('email')
                ->map(fn ($email): string => strtolower(trim((string) $email)))
                ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
                ->unique()
                ->values();

            if ($recipients->isEmpty()) {
                Log::warning('New booking notification skipped because no booking reviewer email is available.', [
                    'booking_id' => $booking->id,
                ]);

                return;
            }

            $mail = $this->adminBookingMail($booking);

            DB::afterCommit(function () use ($booking, $recipients, $mail): void {
                foreach ($recipients as $email) {
                    try {
                        $dedupeKey = 'fleetguard:admin-booking-email:'.$booking->id.':'.hash('sha256', $email);

                        if (! Cache::add($dedupeKey, true)) {
                            continue;
                        }

                        $this->deliverBookingMail($email, $mail);
                    } catch (Throwable $exception) {
                        $this->logFailure($exception, $email, 'admin_booking_received', $booking->id);
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->logFailure($exception, '', 'admin_booking_received', $booking->id);
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

    public function sendPasswordChangedNotification(User $user, int $auditLogId): void
    {
        DB::afterCommit(function () use ($user, $auditLogId): void {
            try {
                if (! Cache::add("fleetguard:password-changed-email:{$auditLogId}", true, now()->addDay())) {
                    return;
                }

                $contactDetails = collect([
                    'Phone' => config('fleetguard.phone'),
                    'Email' => config('fleetguard.email'),
                    'Address' => config('fleetguard.address'),
                ])->map(fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : 'To be added');

                $mail = new FleetGuardMessageMail(
                    subjectLine: 'Your password was changed',
                    preheader: 'Your FleetGuard account password was updated.',
                    heading: 'Your password was changed',
                    intro: 'The password for your FleetGuard account was changed.',
                    details: $contactDetails
                        ->map(fn (string $value, string $label): array => ['label' => $label, 'value' => $value])
                        ->values()
                        ->all(),
                    action: [
                        'label' => 'Reset your password',
                        'url' => route('password.request'),
                    ],
                    note: 'If this was not you, reset your password using the link above and contact FleetGuard.',
                    eventType: 'customer_password_changed',
                );

                Mail::to($user->email)->send($mail);
            } catch (Throwable $exception) {
                $this->logFailure($exception, $user->email, 'customer_password_changed');
            }
        });
    }

    private function queue(string $email, FleetGuardMessageMail $mail): bool
    {
        Mail::to($email)->queue($mail->afterCommit());

        return true;
    }

    private function deliverBookingMail(string $email, FleetGuardMessageMail $mail): bool
    {
        $localWebRequest = config('app.env') === 'local' && ! app()->runningInConsole();

        if ($localWebRequest) {
            Mail::to($email)->send($mail);

            return true;
        }

        return $this->queue($email, $mail);
    }

    private function bookingMail(Booking $booking, string $email, string $event): FleetGuardMessageMail
    {
        $customerName = trim((string) $booking->customer?->full_name) ?: 'there';
        $customerUser = $booking->customer?->user;
        $hasVerifiedAccount = $customerUser?->isCustomer() && $customerUser->hasVerifiedEmail();
        $action = $hasVerifiedAccount
            ? [
                'label' => 'View in My Bookings',
                'url' => route('account.bookings'),
            ]
            : null;
        $callout = $hasVerifiedAccount ? null : [
            'heading' => $event === 'approved' ? 'Want to track this booking?' : 'Want to track or cancel this request?',
            'text' => $event === 'approved'
                ? 'Create an account with the same email address. Your approved booking will be linked after you verify that email.'
                : 'Create an account with the same email address. Your request will be linked after you verify that email.',
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
                'FleetGuard: Your booking is approved',
                "Congratulations, {$customerName}! Your FleetGuard booking has been approved.",
                "Congratulations, {$customerName}!",
                'The FleetGuard admin approved your booking. Your vehicle is reserved for the pickup and return times below.',
                'Keep this email and your booking reference for your records.',
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

    private function adminBookingMail(Booking $booking): FleetGuardMessageMail
    {
        $customer = $booking->customer;
        $vehicle = $booking->vehicle;
        $pickup = $booking->pickup_datetime
            ->copy()
            ->timezone(config('app.timezone'))
            ->format('M j, Y, g:i A');
        $returned = $booking->return_datetime
            ->copy()
            ->timezone(config('app.timezone'))
            ->format('M j, Y, g:i A');

        return new FleetGuardMessageMail(
            subjectLine: 'New booking request: '.$booking->booking_reference,
            preheader: ($customer?->full_name ?? 'A customer').' submitted a booking request.',
            heading: 'New booking request',
            intro: 'A customer submitted a booking request that is waiting for admin review.',
            details: [
                ['label' => 'Booking reference', 'value' => $booking->booking_reference],
                ['label' => 'Customer', 'value' => (string) $customer?->full_name],
                ['label' => 'Email', 'value' => (string) $customer?->email],
                ['label' => 'Mobile', 'value' => (string) $customer?->mobile_number],
                ['label' => 'Vehicle', 'value' => (string) $vehicle?->name],
                ['label' => 'Pickup', 'value' => $booking->pickup_location.' · '.$pickup],
                ['label' => 'Return', 'value' => $booking->return_location.' · '.$returned],
                ['label' => 'Estimated total', 'value' => 'PHP '.number_format((float) $booking->estimated_total, 2)],
                ['label' => 'Status', 'value' => 'Pending approval'],
            ],
            action: [
                'label' => 'Review booking',
                'url' => route('admin.bookings.show', $booking),
            ],
            note: 'This is an automated notification for a new FleetGuard booking request.',
            eventType: 'admin_booking_received',
            bookingId: $booking->id,
        );
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
