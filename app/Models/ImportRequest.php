<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRequest extends Model
{
    protected $fillable = [
        'requested_by',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'new_records',
        'updates',
        'skipped',
        'failed_rows',
        'errors',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'mode',
        'validation_results',
    ];

    protected function casts(): array
    {
        return [
            'validation_results' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRequestRow::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByRequester($query, int $userId)
    {
        return $query->where('requested_by', $userId);
    }

    // ── Status Helpers ─────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-warning text-dark',
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Unknown',
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getValidRowCount(): int
    {
        return $this->rows()->where('action', '!=', 'invalid')->count();
    }

    public function getInvalidRowCount(): int
    {
        return $this->rows()->where('action', 'invalid')->count();
    }

    /**
     * Build per-employee record arrays grouped by import action for audit
     * logging. Returns created, updated, skipped, and failed records, each
     * with the employee name and ID number (and error/reason where relevant).
     */
    public function getAuditRecords(int $limit = 50): array
    {
        $created = [];
        $updated = [];
        $skipped = [];
        $failed  = [];

        foreach ($this->rows as $row) {
            $name     = $row->name ?: '(unknown)';
            $idNumber = $row->id_number ?: '(unknown)';

            switch ($row->action) {
                case 'create':
                    $created[] = ['name' => $name, 'id_number' => $idNumber];
                    break;
                case 'update':
                    $updated[] = ['name' => $name, 'id_number' => $idNumber];
                    break;
                case 'skip':
                    $skipped[] = [
                        'name' => $name,
                        'id_number' => $idNumber,
                        'reason' => 'Skipped during validation.',
                    ];
                    break;
                case 'invalid':
                    $failed[] = [
                        'name' => $name,
                        'id_number' => $idNumber,
                        'error' => $row->error_message ?: 'Invalid record.',
                    ];
                    break;
            }
        }

        return [
            'created_records' => array_slice($created, 0, $limit),
            'updated_records' => array_slice($updated, 0, $limit),
            'skipped_records' => array_slice($skipped, 0, $limit),
            'failed_records'  => array_slice($failed, 0, $limit),
        ];
    }
}
