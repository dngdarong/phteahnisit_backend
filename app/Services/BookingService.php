<?php

namespace App\Services;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     *
     * Room + duplicate-pending checks and the insert happen inside one
     * transaction with the room row locked, so two concurrent requests
     * for the same room/student can't both pass the duplicate check
     * before either has committed.
     */
    public function create(User $student, Room $room, array $data): Booking
    {
        return DB::transaction(function () use ($student, $room, $data) {
            $lockedRoom = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($lockedRoom->status->value !== 'approved' || ! $lockedRoom->available) {
                throw ValidationException::withMessages([
                    'room' => ['This room is not currently available to book.'],
                ]);
            }

            $hasPending = Booking::where('room_id', $lockedRoom->id)
                ->where('student_id', $student->id)
                ->where('status', BookingStatusEnum::Pending)
                ->lockForUpdate()
                ->exists();

            if ($hasPending) {
                throw ValidationException::withMessages([
                    'room' => ['You already have a pending booking request for this room.'],
                ]);
            }

            $booking = Booking::create([
                ...$data,
                'room_id' => $lockedRoom->id,
                'student_id' => $student->id,
                'status' => BookingStatusEnum::Pending,
            ]);

            $this->auditLog->log($student, 'booking.created', $booking);

            return $booking;
        });
    }

    /**
     * Only a still-pending booking can be approved - re-fetched under a
     * row lock so two concurrent approve calls (or an approve racing a
     * reject/cancel) can't both succeed against the same stale status.
     */
    public function approve(User $landlord, Booking $booking): Booking
    {
        return DB::transaction(function () use ($landlord, $booking) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatusEnum::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Only a pending booking can be approved.'],
                ]);
            }

            $locked->update(['status' => BookingStatusEnum::Approved]);
            $this->auditLog->log($landlord, 'booking.approved', $locked);

            return $locked;
        });
    }

    /** Same pending-only transition guard as approve(), under the same lock. */
    public function reject(User $landlord, Booking $booking, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($landlord, $booking, $reason) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatusEnum::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Only a pending booking can be rejected.'],
                ]);
            }

            $locked->update(['status' => BookingStatusEnum::Rejected]);
            $this->auditLog->log($landlord, 'booking.rejected', $locked, ['reason' => $reason]);

            return $locked;
        });
    }

    /**
     * Student cancelling their own request - ownership/pending-only is
     * enforced up front by BookingPolicy::cancel, and re-checked here
     * under a row lock as defense-in-depth against a landlord
     * approving/rejecting the same booking in the moment between the
     * policy check and this update.
     */
    public function cancel(User $student, Booking $booking): Booking
    {
        return DB::transaction(function () use ($student, $booking) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatusEnum::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Only a pending booking can be cancelled.'],
                ]);
            }

            $locked->update(['status' => BookingStatusEnum::Cancelled]);
            $this->auditLog->log($student, 'booking.cancelled', $locked);

            return $locked;
        });
    }
}
