<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomImageFactory extends Factory
{
    protected $model = RoomImage::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'image_path' => 'rooms/' . fake()->uuid() . '.jpg',
            'display_order' => 0,
        ];
    }
}
