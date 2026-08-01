<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Room;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\FavoritePolicy;
use App\Policies\RoomPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Room::class => RoomPolicy::class,
        User::class => UserPolicy::class,
        Favorite::class => FavoritePolicy::class,
        Booking::class => BookingPolicy::class,
        Conversation::class => ConversationPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
