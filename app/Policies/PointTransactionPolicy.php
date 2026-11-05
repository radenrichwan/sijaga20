<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PointTransaction;
use App\Models\User;

class PointTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isGuru();
    }

    public function view(User $user, PointTransaction $transaction): bool
    {
        return $user->isActive()
            && ($user->isGuru()
                || ($user->isSiswa()
                    && $transaction->student?->user_id === $user->id));
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->isGuru();
    }
}
