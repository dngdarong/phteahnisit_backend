<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v0.2: booking = a student's move-in request for a room, reviewed
     * by the landlord (pending -> approved/rejected), modeled after the
     * design bundle's booking flow (student-screens.jsx ScreenBooking).
     * This is a request/reservation record, not a payment or lease -
     * payments remain explicitly out of scope per every spec doc.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->date('move_in_date');
            $table->unsignedSmallInteger('duration_months');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index('room_id');
            $table->index('student_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
