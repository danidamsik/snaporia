<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin()
            || (
                $user->isAdmin()
                && $transaction->order()->whereHas('event', fn ($query) => $query->where('admin_id', $user->id))->exists()
            );
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin() && ! in_array($transaction->status, ['settlement', 'capture'], true);
    }
}
