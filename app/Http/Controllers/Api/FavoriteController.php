<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Favorite;
use App\Models\Room;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favoriteService)
    {
    }

    /** The current student's saved rooms. */
    public function index(Request $request): JsonResponse
    {
        $rooms = Room::query()
            ->whereHas('favorites', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['images', 'favorites' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->latest()
            ->paginate(12);

        return RoomResource::collection($rooms)->response();
    }

    public function toggle(Request $request, Room $room): JsonResponse
    {
        Gate::authorize('create', Favorite::class);

        $isFavorited = $this->favoriteService->toggle($request->user(), $room);

        return response()->json(['is_favorited' => $isFavorited]);
    }
}
