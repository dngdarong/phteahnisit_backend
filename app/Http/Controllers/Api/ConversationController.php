<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Room;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    /** Every conversation this user (student or landlord side) is a participant in. */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = Conversation::query()
            ->where('student_id', $userId)
            ->orWhere('landlord_id', $userId)
            ->with(['room', 'student', 'landlord', 'messages'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return ConversationResource::collection($conversations)->response();
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['room', 'student', 'landlord', 'messages']);
        $this->chatService->markRead($request->user(), $conversation);

        return new ConversationResource($conversation);
    }

    /** Student-initiated: implicitly creates (or reuses) the (room, student) conversation. */
    public function sendToRoom(StoreMessageRequest $request, Room $room): JsonResponse
    {
        // Route-level 'role:student' middleware is the real gate here (only
        // a student can *start* a conversation) - no per-instance ownership
        // check applies since the conversation doesn't exist yet.
        $message = $this->chatService->startOrSend($request->user(), $room, $request->validated('body'));

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    /** Reply within an existing conversation - either participant. */
    public function sendMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('send', $conversation);

        $message = $this->chatService->send($request->user(), $conversation, $request->validated('body'));

        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
