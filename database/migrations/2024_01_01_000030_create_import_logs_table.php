<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->integer('total_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('created_rows')->default(0);
            $table->integer('updated_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->foreignId('imported_by')->constrained('users')->onDelete('restrict');
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index('imported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
