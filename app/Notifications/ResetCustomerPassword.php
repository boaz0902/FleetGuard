<?php

namespace App\Notifications;

use App\Mail\FleetGuardMessageMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetCustomerPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    public function toMail($notifiable): FleetGuardMessageMail
    {
        $expiresIn = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        return (new FleetGuardMessageMail(
            subjectLine: 'Reset your password',
            preheader: 'Use the secure link to reset your FleetGuard password.',
            heading: 'Reset your password',
            intro: 'We received a request to reset the password for your FleetGuard account.',
            action: [
                'label' => 'Reset password',
                'url' => $this->resetUrl($notifiable),
            ],
            note: "This link expires in {$expiresIn} minutes. If you did not request this, ignore this email; your password will not change.",
            eventType: 'password_reset',
        ))->to($notifiable->getEmailForPasswordReset());
    }
}
