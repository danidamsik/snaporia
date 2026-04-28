<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isVisitor();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isSuperAdmin()
            || ($user->isVisitor() && $order->user_id === $user->id)
            || ($user->isAdmin() && $order->event()->where('admin_id', $user->id)->exists());
    }
}
