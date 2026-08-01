<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v0.2: one conversation per (student, room) pair - the design
     * bundle's chat flow always enters from a specific room/booking
     * context, never a bare "new message to a landlord." landlord_id
     * is denormalized onto the conversation (rather than looked up via
     * room.landlord_id every time) so the thread survives even if the
     * room is later reassigned or deleted.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'student_id']);
            $table->index('landlord_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
