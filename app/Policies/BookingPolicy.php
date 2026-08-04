<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    /** A student may only cancel their own, still-pending booking. */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->id === $booking->student_id && $booking->status->value === 'pending';
    }

    /**
     * Approve/reject mirrors the room-approval pattern, just scoped to
     * the landlord who owns the room instead of an admin (v0.2 decision:
     * bookings are reviewed by the room's own landlord, not the admin
     * queue - admins moderate rooms, landlords moderate bookings on
     * their own rooms).
     */
    public function moderate(User $user, Booking $booking): bool
    {
        return $user->isLandlord() && $user->id === $booking->room->landlord_id;
    }
}
