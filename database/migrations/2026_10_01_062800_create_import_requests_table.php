<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->integer('new_records')->default(0);
            $table->integer('updates')->default(0);
            $table->integer('skipped')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->text('errors')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('mode')->default('add'); // add, update, both
            $table->json('validation_results')->nullable();
            $table->timestamps();
        });

        Schema::create('import_request_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_request_id')->constrained('import_requests')->onDelete('cascade');
            $table->integer('row_number');
            $table->string('name');
            $table->string('id_number');
            $table->string('position')->nullable();
            $table->string('employment_type')->nullable();
            $table->date('date_hired')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('image_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('action'); // create, update, skip, invalid
            $table->text('error_message')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['import_request_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_request_rows');
        Schema::dropIfExists('import_requests');
    }
};
