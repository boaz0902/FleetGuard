<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

class SiteSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function view(User $user, SiteSetting $siteSetting): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user, ?SiteSetting $siteSetting = null): bool
    {
        return $user->isAdmin();
    }
}
