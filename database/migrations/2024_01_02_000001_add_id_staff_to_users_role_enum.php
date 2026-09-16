<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB: re-declare the full ENUM to include the new value.
        // SQLite: ENUM is stored as VARCHAR — 'id_staff' is already valid, no ALTER needed.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('administrator','id_staff','user') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET role = 'user' WHERE role = 'id_staff'");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('administrator','user') NOT NULL DEFAULT 'user'");
        }
    }
};
