<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'target_type',
        'target_id',
        'target_identifier',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }
        return $query->where(function ($q) use ($term) {
            $q->where('description', 'like', "%{$term}%")
              ->orWhere('target_identifier', 'like', "%{$term}%")
              ->orWhereHas('user', function ($subQ) use ($term) {
                  $subQ->where('username', 'like', "%{$term}%")
                       ->orWhere('name', 'like', "%{$term}%");
              });
        });
    }

    public function scopeDateFrom($query, ?string $date)
    {
        if (blank($date)) {
            return $query;
        }
        return $query->where('created_at', '>=', $date);
    }

    public function scopeDateTo($query, ?string $date)
    {
        if (blank($date)) {
            return $query;
        }
        return $query->where('created_at', '<=', $date);
    }
}
