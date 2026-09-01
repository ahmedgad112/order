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
            ['email' => 'gad@gmail.com'],
            [
                'name' => 'سوبر أدمن',
                'password' => Hash::make('Ahmedgad@2011'),
                'role' => UserRole::SuperAdmin,
                'counter_name' => null,
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'manager@queue.local'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'role' => UserRole::Manager,
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
        User::query()->updateOrCreate(
            ['email' => 'teller3@queue.local'],
            [
                'name' => 'موظف شباك 3',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 3',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller4@queue.local'],
            [
                'name' => 'موظف شباك 4',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 4',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller5@queue.local'],
            [
                'name' => 'موظف شباك 5',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 5',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller6@queue.local'],
            [
                'name' => 'موظف شباك 6',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 2',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller7@queue.local'],
            [
                'name' => 'موظف شباك 7',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 7',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller8@queue.local'],
            [
                'name' => 'موظف شباك 8',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 8',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller9@queue.local'],
            [
                'name' => 'موظف شباك 9',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 9',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller10@queue.local'],
            [
                'name' => 'موظف شباك 10',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 10',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller11@queue.local'],
            [
                'name' => 'موظف شباك 11',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 11',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller12@queue.local'],
            [
                'name' => 'موظف شباك 12',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 12',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller13@queue.local'],
            [
                'name' => 'موظف شباك 13',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 13',
                'is_active' => true,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'teller14@queue.local'],
            [
                'name' => 'موظف شباك 14',
                'password' => Hash::make('password'),
                'role' => UserRole::Teller,
                'counter_name' => 'شباك 14',
                'is_active' => true,
            ]
        );
    }
}
