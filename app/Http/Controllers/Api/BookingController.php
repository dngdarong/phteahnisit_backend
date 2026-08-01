<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    /** The current student's own booking requests, any status. */
    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()->bookings()->with(['room.images'])->latest()->paginate(12);

        return BookingResource::collection($bookings)->response();
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        Gate::authorize('create', Booking::class);

        $room = Room::findOrFail($request->validated('room_id'));

        $booking = $this->bookingService->create($request->user(), $room, $request->safe()->except('room_id'));

        return (new BookingResource($booking->load('room')))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('cancel', $booking);

        return new BookingResource($this->bookingService->cancel($request->user(), $booking)->load('room'));
    }
}
