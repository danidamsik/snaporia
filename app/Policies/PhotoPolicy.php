<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;

class PhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function view(User $user, Photo $photo): bool
    {
        return $user->isSuperAdmin()
            || ($user->isAdmin() && $photo->event()->where('admin_id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Photo $photo): bool
    {
        return $user->isAdmin()
            && $photo->event()->where('admin_id', $user->id)->exists();
    }
}
