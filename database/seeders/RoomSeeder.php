<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $landlords = User::where('role', RoleEnum::Landlord)->get();

        $landlords->each(function (User $landlord) {
            // Each landlord gets 2-4 rooms
            $roomCount = rand(2, 4);

            Room::factory()
                ->count($roomCount)
                ->for($landlord, 'landlord')
                ->create()
                ->each(function (Room $room) {
                    // Each room gets 3-6 images
                    $imageCount = rand(3, 6);

                    for ($i = 0; $i < $imageCount; $i++) {
                        RoomImage::factory()->create([
                            'room_id' => $room->id,
                            'display_order' => $i,
                        ]);
                    }
                });
        });

        // A few pending rooms awaiting admin approval
        Room::factory()
            ->count(3)
            ->pending()
            ->create();
    }
}
