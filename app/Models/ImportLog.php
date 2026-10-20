<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $table = 'import_logs';

    protected $fillable = [
        'filename',
        'total_rows',
        'successful_rows',
        'created_rows',
        'updated_rows',
        'skipped_rows',
        'failed_rows',
        'imported_by',
        'errors',
        'created_records',
        'updated_records',
        'failed_records',
        'skipped_records',
    ];

    protected $casts = [
        'errors' => 'array',
        'created_records' => 'array',
        'updated_records' => 'array',
        'failed_records' => 'array',
        'skipped_records' => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
