<?php

namespace Database\Seeders;

use App\Services\RolePermissionResolver;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionResolver::class)->seedDefaults();
    }
}
