<?php

namespace Database\Seeders;

use App\Models\User;
use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@example.com', 
            ],
            [
                'name'              => 'Admin',
                'password'          => Hash::make('Admin@123456'), // স্ট্রং ডিফল্ট পাসওয়ার্ড
                'role'              => AuthRole::ADMIN->value,
                'status'            => AuthStatus::APPROVED->value,
                'image'             => null,
                'email_verified_at' => now(),
            ]
        );
    }
}