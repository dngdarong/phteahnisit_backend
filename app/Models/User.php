<?php

namespace App\Models;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleEnum::class,
            'status' => UserStatusEnum::class,
        ];
    }

    /**
     * Rooms owned by this user. Only meaningful when role = landlord,
     * but left unconstrained here — ownership is enforced in the
     * Policy layer, not the relationship itself.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'landlord_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** Only meaningful when role = student - enforced in the Policy layer, same pattern as rooms(). */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'student_id');
    }

    public function conversationsAsStudent(): HasMany
    {
        return $this->hasMany(Conversation::class, 'student_id');
    }

    public function conversationsAsLandlord(): HasMany
    {
        return $this->hasMany(Conversation::class, 'landlord_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::Admin;
    }

    public function isLandlord(): bool
    {
        return $this->role === RoleEnum::Landlord;
    }

    public function isStudent(): bool
    {
        return $this->role === RoleEnum::Student;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatusEnum::Active;
    }
}
