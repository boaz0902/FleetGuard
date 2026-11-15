<?php

namespace App\Console\Commands;

use App\Services\SafeMailDeliveryService;
use Illuminate\Console\Command;

class TestMailDeliveryCommand extends Command
{
    protected $signature = 'fleetguard:mail-test {email : The explicit address for the test message}';

    protected $description = 'Send a branded test email using the configured mail transport';

    public function handle(SafeMailDeliveryService $mail): int
    {
        $email = trim((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid email address.');

            return self::INVALID;
        }

        if (! $mail->sendTest($email)) {
            $this->error('Email delivery failed. Check the mail configuration and sanitized application log.');

            return self::FAILURE;
        }

        $this->info('Test email sent successfully.');

        return self::SUCCESS;
    }
}