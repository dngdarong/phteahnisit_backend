<?php

namespace App\Services;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    /**
     * A student may only book an approved+available room, and may not
     * hold two pending bookings on the same room at once (v0.2 decision
     * - confirmed rather than guessed). They may still have pending
     * bookings across different rooms.
     */
    public function create(User $student, Room $room, array $data): Booking
    {
        if ($room->status->value !== 'approved' || ! $room->available) {
            throw ValidationException::withMessages([
                'room' => ['This room is not currently available to book.'],
            ]);
        }

        $hasPending = Booking::where('room_id', $room->id)
            ->where('student_id', $student->id)
            ->where('status', BookingStatusEnum::Pending)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'room' => ['You already have a pending booking request for this room.'],
            ]);
        }

        $booking = Booking::create([
            ...$data,
            'room_id' => $room->id,
            'student_id' => $student->id,
            'status' => BookingStatusEnum::Pending,
        ]);

        $this->auditLog->log($student, 'booking.created', $booking);

        return $booking;
    }

    public function approve(User $landlord, Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatusEnum::Approved]);
        $this->auditLog->log($landlord, 'booking.approved', $booking);

        return $booking;
    }

    public function reject(User $landlord, Booking $booking, ?string $reason = null): Booking
    {
        $booking->update(['status' => BookingStatusEnum::Rejected]);
        $this->auditLog->log($landlord, 'booking.rejected', $booking, ['reason' => $reason]);

        return $booking;
    }

    /** Student cancelling their own request - ownership/pending-only enforced by BookingPolicy::cancel. */
    public function cancel(User $student, Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatusEnum::Cancelled]);
        $this->auditLog->log($student, 'booking.cancelled', $booking);

        return $booking;
    }
}
