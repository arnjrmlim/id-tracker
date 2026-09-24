<?php

namespace App\Models;

use App\Enums\IdStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class IdRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'id_records';

    /** Valid image source values. */
    public const SOURCE_NETWORK = 'network';
    public const SOURCE_UPLOAD  = 'upload';

    /** Valid employment type values. */
    public const EMPLOYMENT_TYPES = ['Employee', 'Agent'];

    protected $fillable = [
        'name',
        'position',
        'employment_type',
        'id_number',
        'date_hired',
        'birth_date',
        'emergency_contact',
        // Image
        'image_path',
        'image_source',
        'image_upload_path',
        // Signature
        'signature_path',
        'signature_source',
        'signature_upload_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_hired' => 'date',
            'birth_date' => 'date',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function statusHistories()
    {
        return $this->hasMany(IdStatusHistory::class, 'id_record_id')
                    ->orderByDesc('created_at');
    }

    public function latestStatusHistory()
    {
        return $this->hasOne(IdStatusHistory::class, 'id_record_id')
                    ->latestOfMany();
    }

    // ── Image source helpers ───────────────────────────────────────────────────

    public function hasImage(): bool
    {
        return $this->image_source === self::SOURCE_NETWORK
            ? filled($this->image_path)
            : filled($this->image_upload_path);
    }

    public function hasSignature(): bool
    {
        return $this->signature_source === self::SOURCE_NETWORK
            ? filled($this->signature_path)
            : filled($this->signature_upload_path);
    }

    public function imageIsUpload(): bool
    {
        return $this->image_source === self::SOURCE_UPLOAD;
    }

    public function signatureIsUpload(): bool
    {
        return $this->signature_source === self::SOURCE_UPLOAD;
    }

    /**
     * Returns the public URL for an uploaded image, or null for network images.
     */
    public function getImageUploadUrl(): ?string
    {
        if ($this->image_source === self::SOURCE_UPLOAD && filled($this->image_upload_path)) {
            return Storage::disk('public')->url($this->image_upload_path);
        }
        return null;
    }

    public function getSignatureUploadUrl(): ?string
    {
        if ($this->signature_source === self::SOURCE_UPLOAD && filled($this->signature_upload_path)) {
            return Storage::disk('public')->url($this->signature_upload_path);
        }
        return null;
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getStatusEnumAttribute(): ?IdStatus
    {
        return IdStatus::tryFrom($this->status);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        $enum = $this->statusEnum;
        return $enum ? $enum->badgeClass() : 'bg-secondary';
    }

    public function getDateHiredFormattedAttribute(): string
    {
        return $this->date_hired ? $this->date_hired->format('m/d/Y') : '';
    }

    public function getBirthDateFormattedAttribute(): string
    {
        return $this->birth_date ? $this->birth_date->format('m/d/Y') : '';
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('id_number', 'like', "%{$term}%")
              ->orWhere('position', 'like', "%{$term}%");
        });
    }

    public function scopeFilterStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }
        return $query->where('status', $status);
    }

    public function scopeFilterPosition($query, ?string $position)
    {
        if (blank($position)) {
            return $query;
        }
        return $query->where('position', 'like', "%{$position}%");
    }

    public function scopeFilterEmploymentType($query, ?string $type)
    {
        if (blank($type)) {
            return $query;
        }
        return $query->where('employment_type', $type);
    }

    public function scopeFilterDateHiredFrom($query, ?string $date)
    {
        if (blank($date)) {
            return $query;
        }
        return $query->where('date_hired', '>=', $date);
    }

    public function scopeFilterDateHiredTo($query, ?string $date)
    {
        if (blank($date)) {
            return $query;
        }
        return $query->where('date_hired', '<=', $date);
    }
}
