<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserSourceCredential;

class UserSourceCredentialPolicy
{
    public function view(User $user, UserSourceCredential $credential): bool
    {
        return $user->id === $credential->user_id;
    }

    public function update(User $user, UserSourceCredential $credential): bool
    {
        return $this->view($user, $credential);
    }

    public function delete(User $user, UserSourceCredential $credential): bool
    {
        return $this->view($user, $credential);
    }
}
