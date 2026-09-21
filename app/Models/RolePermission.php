<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['role', 'permission', 'allowed'])]
class RolePermission extends Model
{
    protected function casts(): array
    {
        return [
            'permission' => Permission::class,
            'allowed' => 'boolean',
        ];
    }
}
