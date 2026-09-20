<?php

namespace App\Enums;

enum Permission: string
{
    case ManageUsers = 'manage_users';
    case ControlSystem = 'control_system';
    case EditTickets = 'edit_tickets';
    case DeleteTickets = 'delete_tickets';

    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'إدارة المستخدمين',
            self::ControlSystem => 'التحكم في النظام',
            self::EditTickets => 'تعديل التذاكر',
            self::DeleteTickets => 'حذف التذاكر',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isLockedOffFor(UserRole $role): bool
    {
        return $role === UserRole::Teller
            && ($this === self::ManageUsers || $this === self::ControlSystem);
    }

    public function isEditableFor(UserRole $role): bool
    {
        if ($role === UserRole::SuperAdmin) {
            return false;
        }

        return ! $this->isLockedOffFor($role);
    }

    public function defaultFor(UserRole $role): bool
    {
        return match ($this) {
            self::ManageUsers => $role->canManageUsers(),
            self::ControlSystem => $role->canControlSystem(),
            self::EditTickets => $role->canEditTickets(),
            self::DeleteTickets => $role->canDeleteTickets(),
        };
    }
}
