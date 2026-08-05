<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        // A user may edit their own profile via ProfileController; this
        // policy governs the *admin* user-management endpoints only.
        if ($this->isAdminTier($target)) {
            return $user->isSuperAdmin();
        }

        return $user->isAdmin();
    }

    public function disable(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false; // can't disable self
        }

        if ($this->isAdminTier($target)) {
            return $user->isSuperAdmin();
        }

        return $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false; // can't delete self, same as disable
        }

        if ($this->isAdminTier($target)) {
            return $user->isSuperAdmin();
        }

        return $user->isAdmin();
    }

    /**
     * Guards creating a new Admin or SuperAdmin account. Business Rules
     * updated: "Only a Super Admin can create/edit/delete/promote another
     * Admin" - regular Admins can no longer grant admin-tier roles.
     */
    public function createAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    private function isAdminTier(User $target): bool
    {
        return in_array($target->role, [RoleEnum::Admin, RoleEnum::SuperAdmin], true);
    }
}
