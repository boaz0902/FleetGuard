<?php

namespace App\Console\Commands;

use App\Services\SafeMailDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use RuntimeException;

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

        $this->reportQueueWorkerStatus();

        if (! $mail->sendTest($email)) {
            $this->error('Email delivery failed. Check the mail configuration and sanitized application log.');

            return self::FAILURE;
        }

        $this->info('Test email sent successfully.');

        return self::SUCCESS;
    }

    private function reportQueueWorkerStatus(): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        $this->warn('QUEUE_CONNECTION is not sync. A queue worker must be running for queued email delivery; use php artisan queue:work.');

        $status = $this->queueWorkerDetected();

        if ($status === false) {
            $this->warn('No queue worker process was detected.');
        } elseif ($status === null) {
            $this->warn('Queue worker status could not be detected; confirm that php artisan queue:work is running.');
        } else {
            $this->info('Queue worker process detected.');
        }
    }

    private function queueWorkerDetected(): ?bool
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? [
                'powershell.exe',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                '$ErrorActionPreference = "Stop"; $workers = @(Get-CimInstance -ClassName Win32_Process | Where-Object { $_.ProcessName -match "^php(?:-cgi)?\\.exe$" -and $_.CommandLine -match "artisan\\s+queue:(work|listen)" }); [Console]::WriteLine($workers.Count)',
            ]
            : ['ps', '-eo', 'args='];

        try {
            $result = Process::timeout(3)->run($command);
        } catch (ProcessTimedOutException|RuntimeException) {
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $count = filter_var(trim($result->output()), FILTER_VALIDATE_INT);

            return $count === false ? null : $count > 0;
        }

        return preg_match('/\bartisan\s+queue:(?:work|listen)\b/i', $result->output()) === 1;
    }
}
