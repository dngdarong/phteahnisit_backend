<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRoomRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomController extends Controller
{
    public function __construct(private readonly RoomService $roomService)
    {
    }

    /**
     * Public search/browse. FRS: "Only Approved Rooms, Only Available
     * Rooms" appear in search results.
     */
    public function index(SearchRoomRequest $request): JsonResponse
    {
        $query = Room::query()->searchable()->with('images');

        $query->when($request->validated('keyword'), fn ($q, $kw) => $q->where(
            fn ($q2) => $q2->where('title', 'like', "%{$kw}%")->orWhere('description', 'like', "%{$kw}%")
        ));
        $query->when($request->validated('province'), fn ($q, $v) => $q->where('province', $v));
        $query->when($request->validated('district'), fn ($q, $v) => $q->where('district', $v));
        $query->when($request->validated('room_type'), fn ($q, $v) => $q->where('room_type', $v));
        $query->when($request->validated('price_min'), fn ($q, $v) => $q->where('price', '>=', $v));
        $query->when($request->validated('price_max'), fn ($q, $v) => $q->where('price', '<=', $v));

        $query->orderBy(...match ($request->validated('sort')) {
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            default => ['created_at', 'desc'],
        });

        $rooms = $query->paginate($request->validated('per_page') ?? 12);

        return RoomResource::collection($rooms)->response();
    }

    /**
     * Public room detail. Approved-but-unavailable rooms remain
     * viewable (e.g. via a direct/shared link) so a listing doesn't
     * 404 the moment it's rented - they're just excluded from search.
     * Pending/rejected rooms are gated by RoomPolicy::view.
     */
    public function show(Room $room): RoomResource
    {
        Gate::authorize('view', $room);

        return new RoomResource($room->load(['images', 'landlord']));
    }

    /** Landlord's own listings, all statuses (Business Rules: "View only their own listings"). */
    public function mine(Request $request): JsonResponse
    {
        $rooms = $request->user()->rooms()->with('images')->latest()->paginate(12);

        return RoomResource::collection($rooms)->response();
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = $this->roomService->create(
            $request->user(),
            $request->safe()->except('images'),
            $request->file('images', []),
        );

        return (new RoomResource($room))->response()->setStatusCode(201);
    }

    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        Gate::authorize('update', $room);

        $room = $this->roomService->update(
            $request->user(),
            $room,
            $request->safe()->except('images'),
            $request->file('images', []),
        );

        return new RoomResource($room);
    }

    public function destroy(Request $request, Room $room): JsonResponse
    {
        Gate::authorize('delete', $room);

        $this->roomService->delete($request->user(), $room);

        return response()->json(['message' => 'Room deleted.']);
    }
}
