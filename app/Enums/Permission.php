<?php

namespace App\Enums;

use App\Models\Role;

enum Permission: string
{
    case AccessTellerPanel = 'access_teller_panel';
    case AccessAdminPanel = 'access_admin_panel';
    case AccessRegistrations = 'access_registrations';
    case AccessMic = 'access_mic';
    case ManageUsers = 'manage_users';
    case ManageRoles = 'manage_roles';
    case ControlSystem = 'control_system';
    case EditTickets = 'edit_tickets';
    case DeleteTickets = 'delete_tickets';
    case RestoreTickets = 'restore_tickets';

    public function label(): string
    {
        return match ($this) {
            self::AccessTellerPanel => 'لوحة الموظف',
            self::AccessAdminPanel => 'لوحة الإدارة',
            self::AccessRegistrations => 'السجل والأرشيف',
            self::AccessMic => 'الميكروفون',
            self::ManageUsers => 'إدارة المستخدمين',
            self::ManageRoles => 'إدارة الأدوار والصلاحيات',
            self::ControlSystem => 'التحكم في النظام',
            self::EditTickets => 'تعديل التذاكر',
            self::DeleteTickets => 'حذف التذاكر',
            self::RestoreTickets => 'استرجاع التذاكر الملغاة',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AccessTellerPanel,
            self::AccessAdminPanel,
            self::AccessRegistrations,
            self::AccessMic => 'navigation',
            self::ManageUsers,
            self::ManageRoles => 'users_roles',
            self::ControlSystem => 'system',
            self::EditTickets,
            self::DeleteTickets,
            self::RestoreTickets => 'tickets',
        };
    }

    public function groupLabel(): string
    {
        return match ($this->group()) {
            'navigation' => 'الصفحات والتنقل',
            'users_roles' => 'المستخدمون والأدوار',
            'system' => 'النظام والإعدادات',
            'tickets' => 'التذاكر',
            default => 'أخرى',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isLockedOffFor(Role $role): bool
    {
        if (! $role->serves_queue || $role->slug !== Role::SLUG_TELLER) {
            return false;
        }

        return in_array($this, [
            self::ManageUsers,
            self::ManageRoles,
            self::ControlSystem,
        ], true);
    }

    public function isEditableFor(Role $role): bool
    {
        if ($role->is_super_admin) {
            return false;
        }

        return ! $this->isLockedOffFor($role);
    }

    public function defaultFor(Role $role): bool
    {
        if ($role->is_super_admin) {
            return true;
        }

        return match ($role->slug) {
            Role::SLUG_MANAGER => match ($this) {
                self::AccessTellerPanel,
                self::AccessAdminPanel,
                self::AccessRegistrations,
                self::AccessMic,
                self::ManageUsers,
                self::EditTickets => true,
                default => false,
            },
            Role::SLUG_TELLER => $this === self::AccessTellerPanel,
            default => false,
        };
    }
}
