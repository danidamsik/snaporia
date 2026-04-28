<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSuperAdmin() || $user->id === $target->id;
    }

    public function createAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isSuperAdmin()
            && $target->isAdmin()
            && $this->hasNoOperationalData($target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isSuperAdmin()
            && $user->id !== $target->id
            && $this->hasNoOperationalData($target);
    }

    private function hasNoOperationalData(User $target): bool
    {
        return $target->events()->doesntExist() && $target->orders()->doesntExist();
    }
}
