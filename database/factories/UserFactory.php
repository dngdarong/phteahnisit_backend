<?php

namespace Database\Factories;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'phone' => '0' . fake()->numerify('#########'),
            'role' => RoleEnum::Student,
            'status' => UserStatusEnum::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => RoleEnum::Admin]);
    }

    public function landlord(): static
    {
        return $this->state(fn () => ['role' => RoleEnum::Landlord]);
    }

    public function student(): static
    {
        return $this->state(fn () => ['role' => RoleEnum::Student]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => UserStatusEnum::Inactive]);
    }
}
