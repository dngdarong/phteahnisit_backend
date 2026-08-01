<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RoomStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * All routes here sit behind ->middleware(['auth:sanctum', 'active', 'role:admin']),
 * so no per-action role check is repeated below - only the ownership-
 * irrelevant Gate::authorize('moderate', ...) for defense in depth.
 */
class RoomController extends Controller
{
    public function __construct(private readonly RoomService $roomService)
    {
    }

    /** Pending queue - the admin approval dashboard's primary view. */
    public function pending(): JsonResponse
    {
        $rooms = Room::query()->pending()->with(['images', 'landlord'])->latest()->paginate(20);

        return RoomResource::collection($rooms)->response();
    }

    /** All rooms, any status, for the admin rooms index. */
    public function index(Request $request): JsonResponse
    {
        $query = Room::query()->with(['images', 'landlord']);
        $query->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        return RoomResource::collection($query->latest()->paginate(20))->response();
    }

    public function approve(Room $room): RoomResource
    {
        Gate::authorize('moderate', Room::class);

        return new RoomResource($this->roomService->approve(request()->user(), $room));
    }

    public function reject(Request $request, Room $room): RoomResource
    {
        Gate::authorize('moderate', Room::class);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new RoomResource($this->roomService->reject($request->user(), $room, $request->input('reason')));
    }

    /** Permanent removal - distinct from the landlord's soft-delete path. */
    public function forceDestroy(Request $request, Room $room): JsonResponse
    {
        Gate::authorize('moderate', Room::class);

        $this->roomService->forceDelete($request->user(), $room);

        return response()->json(['message' => 'Room permanently removed.']);
    }
}
