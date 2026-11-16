<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\FleetGuardMessageMail;
use App\Models\ContactMessage;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;

class ContactMessagesController extends Controller
{
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $ipKey = 'contact-message-ip:'.hash('sha256', (string) ($request->ip() ?: 'unknown'));
        $emailKey = 'contact-message-email:'.hash('sha256', $email);

        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($emailKey, 3)) {
            RateLimiter::hit($ipKey, 3600);
            RateLimiter::hit($emailKey, 3600);

            return Redirect::back()->with('status', 'Thanks for reaching out. Your message is queued for review and we will reply by email if needed.');
        }

        $submittedAt = (float) $request->input('submitted_at', microtime(true));
        if (microtime(true) - $submittedAt < 3.0) {
            return Redirect::back()->withErrors([
                'message' => 'Please wait a moment before submitting the form again.',
            ])->withInput();
        }

        $customer = null;
        if ($request->user()) {
            $customer = Customer::query()->where('user_id', $request->user()->id)->first();
        }

        $message = ContactMessage::query()->create([
            'reference' => ContactMessage::newReference(),
            'name' => (string) $request->input('name'),
            'email' => $email,
            'phone' => $request->input('phone') !== null && trim((string) $request->input('phone')) !== '' ? trim((string) $request->input('phone')) : null,
            'subject' => (string) $request->input('subject'),
            'message' => (string) $request->input('message'),
            'status' => ContactMessage::STATUS_NEW,
            'user_id' => $request->user()?->id,
            'customer_id' => $customer?->id,
            'ip_hash' => hash('sha256', (string) ($request->ip() ?: 'unknown')),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($emailKey, 3600);

        DB::afterCommit(function () use ($message): void {
            $this->sendAcknowledgement($message);
            $this->sendBusinessNotification($message);
        });

        return Redirect::back()
            ->with('status', 'Your message has been sent. Your reference is '.$message->reference.'. FleetGuard will reply by email.')
            ->with('contact_message_reference', $message->reference);
    }

    private function sendAcknowledgement(ContactMessage $message): void
    {
        $dedupe = 'contact-message:ack:'.$message->id;
        if (! Cache::add($dedupe, true, 86400)) {
            return;
        }

        $mail = new FleetGuardMessageMail(
            subjectLine: 'We received your message',
            preheader: 'FleetGuard has received your contact message.',
            heading: 'We received your message',
            intro: 'Thanks for reaching out. Your message was received and is now in the FleetGuard inbox.',
            details: [
                ['label' => 'Reference', 'value' => $message->reference],
                ['label' => 'Subject', 'value' => $message->subject],
                ['label' => 'Message', 'value' => $message->message],
            ],
            note: 'A member of the FleetGuard team will reply by email if needed.',
            eventType: 'contact_message_ack',
        );

        try {
            Mail::to($message->email)->send($mail);
        } catch (\Throwable $exception) {
            Log::warning('Contact acknowledgement email failed.', [
                'message_id' => $message->id,
                'recipient_domain' => $this->domain($message->email),
                'exception' => $exception::class,
            ]);
        }
    }

    private function sendBusinessNotification(ContactMessage $message): void
    {
        $businessEmail = trim((string) config('fleetguard.email', config('mail.from.address', '')));
        if ($businessEmail === '') {
            Log::info('Contact business notification skipped because no business email is configured.');

            return;
        }

        $dedupe = 'contact-message:business:'.$message->id;
        if (! Cache::add($dedupe, true, 86400)) {
            return;
        }

        $mail = new FleetGuardMessageMail(
            subjectLine: 'New contact message: '.$message->subject,
            preheader: 'A new contact message was received via the public contact form.',
            heading: 'New contact message',
            intro: 'A visitor sent a message through the public contact form.',
            details: [
                ['label' => 'Reference', 'value' => $message->reference],
                ['label' => 'Name', 'value' => $message->name],
                ['label' => 'Email', 'value' => $message->email],
                ['label' => 'Phone', 'value' => $message->phone ?? 'Not provided'],
                ['label' => 'Subject', 'value' => $message->subject],
                ['label' => 'Message', 'value' => $message->message],
            ],
            action: ['label' => 'Open inbox', 'url' => route('admin.messages.index')],
            note: 'This is an automated notification from the FleetGuard contact form.',
            eventType: 'contact_message_business',
        );

        try {
            Mail::to($businessEmail)->send($mail);
        } catch (\Throwable $exception) {
            Log::warning('Contact business notification email failed.', [
                'message_id' => $message->id,
                'recipient_domain' => $this->domain($businessEmail),
                'exception' => $exception::class,
            ]);
        }
    }

    private function domain(string $email): string
    {
        $separator = strrpos($email, '@');

        return $separator === false ? 'unknown' : substr($email, $separator + 1);
    }
}
