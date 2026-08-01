<?php

namespace Database\Factories;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'student_id' => User::factory()->student(),
            'move_in_date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'duration_months' => fake()->numberBetween(1, 12),
            'status' => BookingStatusEnum::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => BookingStatusEnum::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => BookingStatusEnum::Rejected]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => BookingStatusEnum::Cancelled]);
    }
}
