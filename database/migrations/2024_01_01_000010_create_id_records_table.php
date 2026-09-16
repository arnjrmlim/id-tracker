<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_records', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('id_number', 50)->unique()->nullable();
            $table->date('date_hired')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('emergency_contact', 500)->nullable();
            $table->text('image_path')->nullable();
            $table->text('signature_path')->nullable();
            $table->string('status', 50)->default('PENDING');
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_number');
            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_records');
    }
};
