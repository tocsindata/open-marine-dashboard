<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function complete(User $user, Task $task): bool
    {
        return $user->id === $task->user_id;
    }
}
