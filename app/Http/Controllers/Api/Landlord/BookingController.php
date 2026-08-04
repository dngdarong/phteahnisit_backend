<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * All routes here sit behind ->middleware(['auth:sanctum', 'active', 'role:landlord']),
 * so no per-action role check is repeated below - only the ownership
 * check (Gate::authorize('moderate', $booking), which needs the
 * resolved Booking to check its room's landlord_id).
 */
class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    /** Booking requests on this landlord's own rooms. */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()
            ->whereHas('room', fn ($q) => $q->where('landlord_id', $request->user()->id))
            ->with(['room.images', 'student']);

        $query->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        return BookingResource::collection($query->latest()->paginate(20))->response();
    }

    public function approve(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('moderate', $booking);

        return new BookingResource($this->bookingService->approve($request->user(), $booking)->load(['room', 'student']));
    }

    public function reject(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('moderate', $booking);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new BookingResource(
            $this->bookingService->reject($request->user(), $booking, $request->input('reason'))->load(['room', 'student']),
        );
    }
}
