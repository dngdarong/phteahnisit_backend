<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rooms table.
     *
     * One row per rental listing. Belongs to a landlord (users.id).
     * `status` drives the admin approval workflow; `available` is a
     * separate flag so a landlord can mark an approved room as
     * temporarily rented out without losing its approved status.
     *
     * province/district/commune are kept as plain strings for v0.1
     * (no dedicated locations table) to keep the MVP simple — see
     * docs/DATABASE_DOCUMENTATION.md for the future normalization path.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('province', 100);
            $table->string('district', 100);
            $table->string('commune', 100)->nullable();
            $table->string('address');
            $table->enum('room_type', ['single_room', 'shared_room', 'studio', 'apartment'])
                ->default('single_room');
            $table->boolean('available')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index('landlord_id');
            $table->index('price');
            $table->index('province');
            $table->index('district');
            $table->index('room_type');
            $table->index('status');
            // Composite index for the hot path: "browse approved & available rooms"
            $table->index(['status', 'available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
