<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Per the design bundle's chat flow: "only the two participants can
 * read/write to a conversation" - no admin-initiated messages, no
 * group chat, and (deliberately) no admin visibility either. Unlike
 * RoomPolicy, there is no admin bypass here.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }
}
