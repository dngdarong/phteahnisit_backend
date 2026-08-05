<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Admin = 'admin';
    case Landlord = 'landlord';
    case Student = 'student';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Landlord => 'Landlord',
            self::Student => 'Student',
            self::SuperAdmin => 'Super Admin',
        };
    }
}
