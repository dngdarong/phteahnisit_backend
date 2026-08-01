<?php

namespace App\Policies;

use App\Models\Favorite;
use App\Models\User;

/**
 * Favorites are a student-only concept (v0.2 decision, explicitly
 * confirmed rather than guessed): a landlord/admin favoriting a room
 * makes no product sense, so it's blocked here at the policy layer,
 * not just hidden in the UI - same defense-in-depth pattern as v0.1's
 * UpdateRoomRequest double-checking what route middleware restricts.
 */
class FavoritePolicy
{
    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $user->id === $favorite->user_id;
    }
}
