<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v0.2: student favorites/saved rooms. Only meaningful for students,
     * but not role-constrained at the DB level (enforced in the
     * FavoritePolicy instead) - same pattern as landlord_id on rooms.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'room_id']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
