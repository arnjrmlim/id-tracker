<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name'      => 'System Administrator',
                'email'     => 'admin@id-tracker.local',
                'password'  => Hash::make('Admin@1234'),
                'role'      => UserRole::ADMINISTRATOR,
                'is_active' => true,
            ]
        );
    }
}
