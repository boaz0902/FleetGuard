<?php

namespace App\Notifications;

use App\Mail\FleetGuardMessageMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class VerifyCustomerEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function toMail($notifiable): FleetGuardMessageMail
    {
        $expiresIn = (int) config('auth.verification.expire', 60);

        return (new FleetGuardMessageMail(
            subjectLine: 'Verify your email',
            preheader: 'Verify your FleetGuard account email address.',
            heading: 'Verify your email',
            intro: 'Please verify your email address to complete your FleetGuard account setup.',
            action: [
                'label' => 'Verify email address',
                'url' => $this->verificationUrl($notifiable),
            ],
            note: "This link expires in {$expiresIn} minutes. If you did not create an account, you can ignore this message.",
            eventType: 'email_verification',
        ))->to($notifiable->getEmailForVerification());
    }
}
