<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_status_histories', function (Blueprint $table) {
            // New field: the date the status should officially take effect.
            // Completely separate from created_at (which records when the
            // system action actually happened).  Existing rows get NULL.
            $table->date('effective_status_date')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('id_status_histories', function (Blueprint $table) {
            $table->dropColumn('effective_status_date');
        });
    }
};
