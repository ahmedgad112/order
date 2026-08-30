<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@queue.local'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'counter_name' => null,
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'teller1@queue.local'],
            [
                'name' => 'موظف شباك 1',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 1',
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'teller2@queue.local'],
            [
                'name' => 'موظف شباك 2',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 2',
                'is_active' => true,
            ]
        );
    }
}
