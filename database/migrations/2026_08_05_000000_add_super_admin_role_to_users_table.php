<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the `role` MySQL ENUM column on an already-existing database to
 * add 'super_admin'. The base users migration
 * (2024_01_01_000001_create_users_table.php) already lists 'super_admin'
 * directly in its enum() definition, so a fresh install/test run (SQLite,
 * migrate:fresh) never needs this ALTER - it exists solely to bring an
 * existing MySQL database's already-created column in line, hence the
 * mysql-only guard (SQLite has no ENUM/MODIFY COLUMN equivalent, and
 * doctrine/dbal - required for a portable ->change() - isn't installed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'landlord', 'student', 'super_admin') NOT NULL DEFAULT 'student'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Demote any super_admin rows before shrinking the enum, so the
        // ALTER never fails against existing data.
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'landlord', 'student') NOT NULL DEFAULT 'student'"
        );
    }
};
