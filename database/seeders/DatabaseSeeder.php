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

        $demoTellers = User::query()
            ->where('role', UserRole::Teller)
            ->where('email', 'like', 'teller%@queue.local')
            ->get();

        foreach ($demoTellers as $teller) {
            $teller->tokens()->delete();
            $teller->delete();
        }
    }
}
