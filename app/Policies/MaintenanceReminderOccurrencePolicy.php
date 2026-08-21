<?php

namespace App\Policies;

use App\Models\MaintenanceReminderOccurrence;
use App\Models\User;

class MaintenanceReminderOccurrencePolicy
{
    public function acknowledge(User $user, MaintenanceReminderOccurrence $occurrence): bool
    {
        return $this->isOwnedBy($user, $occurrence);
    }

    public function dismiss(User $user, MaintenanceReminderOccurrence $occurrence): bool
    {
        return $this->isOwnedBy($user, $occurrence);
    }

    private function isOwnedBy(User $user, MaintenanceReminderOccurrence $occurrence): bool
    {
        return MaintenanceReminderOccurrence::query()
            ->ownedBy($user)
            ->whereKey($occurrence->getKey())
            ->exists();
    }
}
