<?php

namespace App\Services;

use App\Enums\RoomStatusEnum;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoomService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    /**
     * @param array<UploadedFile> $images
     */
    public function create(User $landlord, array $data, array $images = []): Room
    {
        return DB::transaction(function () use ($landlord, $data, $images) {
            $room = Room::create([
                ...$data,
                'landlord_id' => $landlord->id,
                'status' => RoomStatusEnum::Pending, // every new room starts pending
            ]);

            $this->attachImages($room, $images);

            $this->auditLog->log($landlord, 'room.created', $room);

            return $room->load('images');
        });
    }

    /**
     * Business Rules doc: "If an Approved room is edited, the room
     * automatically becomes Pending again until reviewed by an Admin."
     * Availability is intentionally left untouched by this reset - an
     * edit shouldn't silently pull an otherwise-fine listing out of
     * search results while it waits for re-approval.
     *
     * @param array<UploadedFile> $newImages
     */
    public function update(User $actor, Room $room, array $data, array $newImages = []): Room
    {
        return DB::transaction(function () use ($actor, $room, $data, $newImages) {
            $wasApproved = $room->status === RoomStatusEnum::Approved;

            $room->fill($data);

            if ($wasApproved) {
                $room->status = RoomStatusEnum::Pending;
            }

            $room->save();

            if (! empty($newImages)) {
                $this->attachImages($room, $newImages);
            }

            $this->auditLog->log($actor, 'room.updated', $room, [
                'reverted_to_pending' => $wasApproved,
            ]);

            return $room->load('images');
        });
    }

    /** Landlord/admin soft delete. Admin hard-delete is a separate method. */
    public function delete(User $actor, Room $room): void
    {
        $room->delete();
        $this->auditLog->log($actor, 'room.deleted', $room);
    }

    /** Admin-only permanent removal (Business Rules: "Admins may permanently remove listings"). */
    public function forceDelete(User $admin, Room $room): void
    {
        foreach ($room->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $roomId = $room->id;
        $room->forceDelete();

        $this->auditLog->log($admin, 'room.force_deleted', null, ['room_id' => $roomId]);
    }

    public function approve(User $admin, Room $room): Room
    {
        $room->update(['status' => RoomStatusEnum::Approved]);
        $this->auditLog->log($admin, 'room.approved', $room);

        return $room;
    }

    public function reject(User $admin, Room $room, ?string $reason = null): Room
    {
        $room->update(['status' => RoomStatusEnum::Rejected]);
        $this->auditLog->log($admin, 'room.rejected', $room, ['reason' => $reason]);

        return $room;
    }

    /**
     * @param array<UploadedFile> $images
     */
    private function attachImages(Room $room, array $images): void
    {
        $startOrder = $room->images()->count();

        foreach (array_values($images) as $index => $image) {
            $path = $image->store('rooms', 'public');

            RoomImage::create([
                'room_id' => $room->id,
                'image_path' => $path,
                'display_order' => $startOrder + $index,
            ]);
        }
    }
}
