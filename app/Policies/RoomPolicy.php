<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /** Anyone (including guests) can view an approved room - checked in controller via ->approved() scope. */
    public function view(?User $user, Room $room): bool
    {
        if ($room->status->value === 'approved') {
            return true;
        }

        // Pending/rejected rooms: only the owning landlord or an admin.
        return $user && ($user->isAdmin() || $user->id === $room->landlord_id);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    /**
     * Ownership rule, verbatim from the Auth doc:
     * "A landlord cannot edit another landlord's room."
     */
    public function update(User $user, Room $room): bool
    {
        return $user->isAdmin() || ($user->isLandlord() && $user->id === $room->landlord_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->isAdmin() || ($user->isLandlord() && $user->id === $room->landlord_id);
    }

    /** Only admins approve/reject - separate from general update. */
    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }
}
