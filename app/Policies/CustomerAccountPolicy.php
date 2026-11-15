<?php

namespace App\Policies;

use App\Models\User;

class CustomerAccountPolicy
{
    public function viewCustomerAccount(User $viewer, User $account): bool
    {
        return $viewer->isCustomer() && $viewer->is($account);
    }
}
