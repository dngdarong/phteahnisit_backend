<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::factory()->admin()->create([
            'name' => 'Admin Phteahnisit',
            'email' => 'dngdarong@gmail.com',
            'password' => bcrypt('Darong@123'),
            'phone' => '012345678',
        ]);

        // Sample landlords with realistic Cambodian names
        $landlordNames = [
            'Sok Dara', 'Chan Sopheap', 'Heng Vutha', 'Kim Sreymom',
            'Ly Bunthoeun', 'Pich Sokha', 'Vann Chandara', 'Meas Reaksmey',
        ];

        foreach ($landlordNames as $name) {
            User::factory()->landlord()->create(['name' => $name]);
        }

        // Sample students with realistic Cambodian names
        $studentNames = [
            'Ros Piseth', 'Chea Kunthea', 'Lim Sokun', 'Nov Malis',
            'Ouk Rithy', 'Prak Sophany', 'Seng Vichet', 'Tep Chenda',
            'Yin Sreyneang', 'Un Vanna',
        ];

        foreach ($studentNames as $name) {
            User::factory()->student()->create(['name' => $name]);
        }
    }
}
