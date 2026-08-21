<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VesselPosition;

class VesselPositionPolicy
{
    public function view(User $user, VesselPosition $position): bool
    {
        return VesselPosition::query()
            ->ownedBy($user)
            ->whereKey($position->getKey())
            ->exists();
    }

    public function update(User $user, VesselPosition $position): bool
    {
        return $this->view($user, $position);
    }

    public function delete(User $user, VesselPosition $position): bool
    {
        return $this->view($user, $position);
    }
}
