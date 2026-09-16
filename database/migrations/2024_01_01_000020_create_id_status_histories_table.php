<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_record_id')->constrained('id_records')->onDelete('cascade');
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->foreignId('changed_by')->constrained('users')->onDelete('restrict');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('id_record_id');
            $table->index('changed_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_status_histories');
    }
};
