<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_records', function (Blueprint $table) {
            // Tracks which storage method was used for the ID front image.
            // 'network' = stored path (e.g. Z:\...),  'upload' = Laravel-managed file
            $table->string('image_source', 20)->nullable()->after('image_path');

            // Stores the relative Laravel storage path when image_source = 'upload'
            // e.g.  id-images/2026/09/200473_abc123.png
            $table->text('image_upload_path')->nullable()->after('image_source');

            // Same pair for signature
            $table->string('signature_source', 20)->nullable()->after('signature_path');
            $table->text('signature_upload_path')->nullable()->after('signature_source');
        });
    }

    public function down(): void
    {
        Schema::table('id_records', function (Blueprint $table) {
            $table->dropColumn([
                'image_source',
                'image_upload_path',
                'signature_source',
                'signature_upload_path',
            ]);
        });
    }
};
