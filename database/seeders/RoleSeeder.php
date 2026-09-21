<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Services\RolePermissionResolver;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::seedSystemRoles();
        app(RolePermissionResolver::class)->seedDefaults();
    }
}
