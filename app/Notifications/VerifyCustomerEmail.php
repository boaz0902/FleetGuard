<?php

namespace App\Notifications;

use App\Mail\CustomerAccountActionMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VerifyCustomerEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] private readonly string $code)
    {
        $this->afterCommit();
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): CustomerAccountActionMail
    {
        return (new CustomerAccountActionMail(
            subjectLine: 'Your FleetGuard verification code: '.$this->code,
            preheader: 'Your FleetGuard verification code is '.$this->code.'.',
            heading: 'Verify your email',
            intro: 'We received a request to verify the email address for your FleetGuard account. Enter this code on the verification page.',
            code: $this->code,
            note: 'This code expires in 5 minutes. If you did not request it, you can safely ignore this email.',
        ))->to($notifiable->getEmailForVerification());
    }
}
