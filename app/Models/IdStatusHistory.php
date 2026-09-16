<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdStatusHistory extends Model
{
    protected $table = 'id_status_histories';

    protected $fillable = [
        'id_record_id',
        'old_status',
        'new_status',
        'changed_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function idRecord()
    {
        return $this->belongsTo(IdRecord::class, 'id_record_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
