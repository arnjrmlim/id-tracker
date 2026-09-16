<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->tinyInteger('weekly_day')->nullable()->comment('0=Sun … 6=Sat');
            $table->tinyInteger('monthly_day')->nullable()->comment('1-28');
            $table->time('backup_time')->default('02:00:00');
            $table->string('backup_path', 1000)->nullable();
            $table->boolean('include_uploaded_files')->default(false);
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_histories', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['manual', 'automatic'])->default('manual');
            $table->string('filename')->nullable();
            $table->string('path', 1000)->nullable();
            $table->unsignedBigInteger('size')->default(0)->comment('bytes');
            $table->enum('status', ['success', 'failed', 'running'])->default('running');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_histories');
        Schema::dropIfExists('backup_settings');
    }
};
