<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit logs table.
     *
     * Added to satisfy the Audit Rules in the Business Rules doc and the
     * "every approval action must be recorded" requirement in the FRS —
     * neither was covered by the original three-table DB design.
     *
     * Generic (action, subject_type, subject_id) shape so any future
     * entity (bookings, payments, etc.) can be audited without a schema
     * change. `actor_id` is nullable so system/automated actions and
     * failed-login attempts (no authenticated user yet) can still log.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 100); // e.g. 'room.approved', 'user.login'
            $table->string('subject_type')->nullable(); // e.g. 'Room', 'User'
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable(); // e.g. old/new values, IP, reason
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_id');
            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
