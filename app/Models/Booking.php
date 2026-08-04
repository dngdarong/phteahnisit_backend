<?php

namespace App\Models;

use App\Enums\BookingStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'student_id',
        'move_in_date',
        'duration_months',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'move_in_date' => 'date',
            'duration_months' => 'integer',
            'status' => BookingStatusEnum::class,
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
