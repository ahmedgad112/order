<?php

namespace App\Enums;

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

    public function canAccessAdminPanel(): bool
    {
        return $this === self::SuperAdmin || $this === self::Manager;
    }

    public function canManageUsers(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canControlSystem(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canEditTickets(): bool
    {
        return $this === self::SuperAdmin || $this === self::Manager;
    }

    public function canDeleteTickets(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::SuperAdmin => [self::SuperAdmin, self::Manager, self::Teller],
            self::Manager => [self::Teller],
            self::Teller => [],
        };
    }

    public function canAssign(self $role): bool
    {
        return in_array($role, $this->assignableRoles(), true);
    }

    public function canManage(self $target): bool
    {
        return match ($this) {
            self::SuperAdmin => true,
            self::Manager => $target === self::Teller,
            self::Teller => false,
        };
    }
}
