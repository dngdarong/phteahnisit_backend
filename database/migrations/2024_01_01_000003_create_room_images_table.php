<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Room images table.
     *
     * Stores only the image path (actual files live on disk / object
     * storage, referenced via Laravel's filesystem disks). display_order
     * lets the gallery be sorted without relying on row insertion order.
     * No soft deletes here — deleting an image is a hard delete; the
     * parent room's soft delete does not need to be reflected on images.
     */
    public function up(): void
    {
        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')
                ->constrained('rooms')
                ->onDelete('cascade');
            $table->string('image_path');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_images');
    }
};
