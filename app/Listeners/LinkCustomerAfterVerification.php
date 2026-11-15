<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\CustomerAccountService;
use Illuminate\Auth\Events\Verified;

class LinkCustomerAfterVerification
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->accounts->linkExistingCustomerAfterVerification($event->user);
        }
    }
}
