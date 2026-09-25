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
        Schema::table('id_records', function (Blueprint $table) {
            // Approval workflow fields
            $table->enum('request_status', ['pending', 'approved', 'rejected'])->nullable()->after('status');
            $table->foreignId('requested_by')->nullable()->constrained('users')->after('request_status');
            $table->timestamp('requested_at')->nullable()->after('requested_by');
            $table->foreignId('approved_by')->nullable()->constrained('users')->after('requested_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');

            // Indexes for filtering
            $table->index('request_status');
            $table->index('requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('id_records', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropIndex(['request_status']);
            $table->dropIndex(['requested_by']);
            $table->dropColumn([
                'request_status',
                'requested_by',
                'requested_at',
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
