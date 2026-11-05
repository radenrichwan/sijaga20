<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && ($user->isGuru() || $user->isOperasional());
    }

    public function view(User $user, Student $student): bool
    {
        return $user->isActive()
            && ($user->isGuru()
                || $user->isOperasional()
                || ($user->isSiswa() && $student->user_id === $user->id));
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->isGuru();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isActive() && $user->isGuru();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isActive() && $user->isGuru();
    }
}
