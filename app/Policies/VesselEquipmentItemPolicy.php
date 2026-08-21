<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VesselEquipmentItem;

class VesselEquipmentItemPolicy
{
    public function view(User $user, VesselEquipmentItem $equipmentItem): bool
    {
        return VesselEquipmentItem::query()
            ->ownedBy($user)
            ->whereKey($equipmentItem->getKey())
            ->exists();
    }

    public function update(User $user, VesselEquipmentItem $equipmentItem): bool
    {
        return $this->view($user, $equipmentItem);
    }
}
