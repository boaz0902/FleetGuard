<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerAccountActionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $preheader,
        public string $heading,
        public string $intro,
        public ?string $code = null,
        public ?array $action = null,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-account-action',
            text: 'emails.text.customer-account-action',
            with: [
                'businessName' => config('fleetguard.name', 'FleetGuard'),
                'logoUrl' => asset('favicon.svg'),
                'preheader' => $this->preheader,
                'heading' => $this->heading,
                'intro' => $this->intro,
                'code' => $this->code,
                'action' => $this->action,
                'note' => $this->note,
            ],
        );
    }
}
