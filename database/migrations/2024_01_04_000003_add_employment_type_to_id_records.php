<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_records', function (Blueprint $table) {
            // Nullable so existing records are not affected.
            // Allowed values: 'Employee' | 'Agent' | NULL
            $table->string('employment_type', 20)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('id_records', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
