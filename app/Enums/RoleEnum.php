<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Admin = 'admin';
    case Landlord = 'landlord';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Landlord => 'Landlord',
            self::Student => 'Student',
        };
    }
}
