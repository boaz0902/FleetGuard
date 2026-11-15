<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FleetGuardMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $preheader,
        public string $heading,
        public string $intro,
        public array $details = [],
        public ?array $action = null,
        public ?array $callout = null,
        public ?string $note = null,
        public string $eventType = 'email',
        public ?int $bookingId = null,
    ) {}

    public function send($mailer)
    {
        try {
            return parent::send($mailer);
        } catch (Throwable $exception) {
            if ($this->eventType === 'mail_test') {
                throw $exception;
            }

            $recipient = $this->to[0]['address'] ?? '';
            $separator = strrpos($recipient, '@');

            Log::warning('Email delivery failed.', [
                'exception' => $exception::class,
                'event' => $this->eventType,
                'booking_id' => $this->bookingId,
                'recipient_domain' => $separator === false ? 'unknown' : substr($recipient, $separator + 1),
            ]);

            return null;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        $contactLines = collect([
            'Phone' => config('fleetguard.phone'),
            'Email' => config('fleetguard.email'),
            'Address' => config('fleetguard.address'),
        ])->filter(fn ($value): bool => is_string($value) && trim($value) !== '');

        return new Content(
            view: 'emails.message',
            text: 'emails.text.message',
            with: [
                'businessName' => config('fleetguard.name', 'FleetGuard'),
                'contactLines' => $contactLines,
                'preheader' => $this->preheader,
                'heading' => $this->heading,
                'intro' => $this->intro,
                'details' => $this->details,
                'action' => $this->action,
                'callout' => $this->callout,
                'note' => $this->note,
            ],
        );
    }
}
