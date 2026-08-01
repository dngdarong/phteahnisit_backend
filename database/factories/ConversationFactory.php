<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        $room = Room::factory()->create();

        return [
            'room_id' => $room->id,
            'student_id' => User::factory()->student(),
            'landlord_id' => $room->landlord_id,
        ];
    }
}
