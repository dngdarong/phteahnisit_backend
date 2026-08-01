<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\Room;
use App\Models\User;

class FavoriteService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    /** Toggle on/off, not just add - returns true if now favorited, false if removed. */
    public function toggle(User $student, Room $room): bool
    {
        $favorite = Favorite::where('user_id', $student->id)->where('room_id', $room->id)->first();

        if ($favorite) {
            $favorite->delete();
            $this->auditLog->log($student, 'favorite.removed', $room);

            return false;
        }

        Favorite::create(['user_id' => $student->id, 'room_id' => $room->id]);
        $this->auditLog->log($student, 'favorite.added', $room);

        return true;
    }
}
