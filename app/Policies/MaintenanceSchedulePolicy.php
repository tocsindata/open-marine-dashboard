<?php

namespace App\Policies;

use App\Models\MaintenanceSchedule;
use App\Models\User;

class MaintenanceSchedulePolicy
{
    public function view(User $user, MaintenanceSchedule $schedule): bool
    {
        return MaintenanceSchedule::query()
            ->ownedBy($user)
            ->whereKey($schedule->getKey())
            ->exists();
    }

    public function update(User $user, MaintenanceSchedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }
}
