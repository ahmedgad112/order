<?php

namespace App\Enums;

/**
 * System role slug constants kept for factories, seeders, and legacy comparisons.
 * Runtime role definitions live in the roles table via App\Models\Role.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Teller = 'teller';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'سوبر أدمن',
            self::Manager => 'مدير',
            self::Teller => 'موظف',
        };
    }
}
