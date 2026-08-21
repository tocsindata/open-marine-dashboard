<?php

namespace App\Policies;

use App\Models\MaintenanceCompletion;
use App\Models\User;

class MaintenanceCompletionPolicy
{
    public function reverse(User $user, MaintenanceCompletion $completion): bool
    {
        return MaintenanceCompletion::query()
            ->ownedBy($user)
            ->whereKey($completion->getKey())
            ->exists();
    }
}
