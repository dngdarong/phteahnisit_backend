<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->shouldSeedDemoData()) {
            return;
        }

        $this->seedAdmin();
        $this->seedSuperAdmin();

        $this->seedDemoUsers([
            'Sok Dara', 'Chan Sopheap', 'Heng Vutha', 'Kim Sreymom',
            'Ly Bunthoeun', 'Pich Sokha', 'Vann Chandara', 'Meas Reaksmey',
        ], RoleEnum::Landlord);

        $this->seedDemoUsers([
            'Ros Piseth', 'Chea Kunthea', 'Lim Sokun', 'Nov Malis',
            'Ouk Rithy', 'Prak Sophany', 'Seng Vichet', 'Tep Chenda',
            'Yin Sreyneang', 'Un Vanna',
        ], RoleEnum::Student);
    }

    private function shouldSeedDemoData(): bool
    {
        return app()->environment(['local', 'testing'])
            || filter_var(config('phteahnisit.seed_demo_data'), FILTER_VALIDATE_BOOL);
    }

    private function seedAdmin(): void
    {
        User::updateOrCreate(
            ['email' => config('phteahnisit.demo.admin_email')],
            [
                'name' => config('phteahnisit.demo.admin_name'),
                'password' => config('phteahnisit.demo.default_password'),
                'phone' => config('phteahnisit.demo.admin_phone'),
                'role' => RoleEnum::Admin,
                'status' => UserStatusEnum::Active,
            ]
        );
    }

    private function seedSuperAdmin(): void
    {
        User::updateOrCreate(
            ['email' => config('phteahnisit.demo.super_admin_email')],
            [
                'name' => config('phteahnisit.demo.super_admin_name'),
                'password' => config('phteahnisit.demo.default_password'),
                'phone' => config('phteahnisit.demo.super_admin_phone'),
                'role' => RoleEnum::SuperAdmin,
                'status' => UserStatusEnum::Active,
            ]
        );
    }

    /**
     * @param  array<int, string>  $names
     */
    private function seedDemoUsers(array $names, RoleEnum $role): void
    {
        foreach ($names as $index => $name) {
            $email = sprintf('%s.%s@phteahnisit.test', $role->value, Str::slug($name));

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => config('phteahnisit.demo.default_password'),
                    'phone' => sprintf('012%06d', $index),
                    'role' => $role,
                    'status' => UserStatusEnum::Active,
                ]
            );
        }
    }
}
