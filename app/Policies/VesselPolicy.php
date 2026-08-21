<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vessel;

class VesselPolicy
{
    public function view(User $user, Vessel $vessel): bool
    {
        return $user->id === $vessel->owner_id;
    }

    public function update(User $user, Vessel $vessel): bool
    {
        return $user->id === $vessel->owner_id;
    }

    public function delete(User $user, Vessel $vessel): bool
    {
        return $user->id === $vessel->owner_id;
    }

    public function restore(User $user, Vessel $vessel): bool
    {
        return $user->id === $vessel->owner_id;
    }
}
