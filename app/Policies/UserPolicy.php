<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        // A user may edit their own profile via ProfileController; this
        // policy governs the *admin* user-management endpoints only.
        return $user->isAdmin();
    }

    public function disable(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->id !== $target->id; // can't disable self
    }

    public function createAdmin(User $user): bool
    {
        // Business Rules: "Only an existing Admin can create another Admin."
        return $user->isAdmin();
    }
}
