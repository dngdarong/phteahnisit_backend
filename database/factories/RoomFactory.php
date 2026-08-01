<?php

namespace Database\Factories;

use App\Enums\RoomStatusEnum;
use App\Enums\RoomTypeEnum;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    private array $provinces = [
        'Phnom Penh', 'Siem Reap', 'Battambang', 'Kampong Cham',
        'Preah Sihanouk', 'Kandal', 'Kampong Speu', 'Takeo',
    ];

    private array $districts = [
        'Chamkar Mon', 'Toul Kork', 'Daun Penh', 'Sen Sok',
        'Mean Chey', 'Russey Keo', 'Por Sen Chey', 'Chroy Changvar',
    ];

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->landlord(),
            'title' => 'Room for rent near ' . fake()->streetName(),
            'description' => fake()->paragraph(4),
            'price' => fake()->numberBetween(30, 300),
            'province' => fake()->randomElement($this->provinces),
            'district' => fake()->randomElement($this->districts),
            'commune' => fake()->word() . ' Commune',
            'address' => fake()->streetAddress(),
            'room_type' => fake()->randomElement(RoomTypeEnum::cases()),
            'available' => fake()->boolean(80),
            'status' => RoomStatusEnum::Approved,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => RoomStatusEnum::Pending]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => RoomStatusEnum::Rejected]);
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['available' => false]);
    }
}
