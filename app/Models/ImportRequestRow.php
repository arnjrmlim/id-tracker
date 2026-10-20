<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRequestRow extends Model
{
    protected $fillable = [
        'import_request_id',
        'row_number',
        'name',
        'id_number',
        'position',
        'employment_type',
        'date_hired',
        'birth_date',
        'emergency_contact',
        'image_path',
        'signature_path',
        'action',
        'error_message',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'date_hired' => 'date',
            'birth_date' => 'date',
            'raw_data' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function importRequest(): BelongsTo
    {
        return $this->belongsTo(ImportRequest::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isValid(): bool
    {
        return $this->action !== 'invalid';
    }

    public function isCreate(): bool
    {
        return $this->action === 'create';
    }

    public function isUpdate(): bool
    {
        return $this->action === 'update';
    }

    public function isSkip(): bool
    {
        return $this->action === 'skip';
    }

    public function getActionBadgeClass(): string
    {
        return match ($this->action) {
            'create' => 'bg-success',
            'update' => 'bg-info text-dark',
            'skip' => 'bg-secondary',
            'invalid' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'create' => 'Create',
            'update' => 'Update',
            'skip' => 'Skip',
            'invalid' => 'Invalid',
            default => 'Unknown',
        };
    }
}
