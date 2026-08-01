<?php

namespace App\Models;

use App\Enums\RoomStatusEnum;
use App\Enums\RoomTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'landlord_id',
        'title',
        'description',
        'price',
        'province',
        'district',
        'commune',
        'address',
        'latitude',
        'longitude',
        'room_type',
        'available',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'available' => 'boolean',
            'room_type' => RoomTypeEnum::class,
            'status' => RoomStatusEnum::class,
        ];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('display_order');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** Publicly visible: approved status, regardless of availability. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', RoomStatusEnum::Approved);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RoomStatusEnum::Pending);
    }

    /** Search-result visible: approved AND available (Business Rules: Search Rules). */
    public function scopeSearchable(Builder $query): Builder
    {
        return $query->approved()->where('available', true);
    }

    /** v0.2 map view: same visibility as search, plus a pinned location. */
    public function scopeOnMap(Builder $query): Builder
    {
        return $query->searchable()->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function scopeInProvince(Builder $query, string $province): Builder
    {
        return $query->where('province', $province);
    }
}
