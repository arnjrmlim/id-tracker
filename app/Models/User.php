<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'is_active'         => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function statusHistories()
    {
        return $this->hasMany(IdStatusHistory::class, 'changed_by');
    }

    public function importLogs()
    {
        return $this->hasMany(ImportLog::class, 'imported_by');
    }

    // ── Role helpers ───────────────────────────────────────────────────────────

    /** Full administrator — all permissions. */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMINISTRATOR;
    }

    /** ID Staff — can create/import/export/view but cannot edit/delete/change status. */
    public function isIdStaff(): bool
    {
        return $this->role === UserRole::ID_STAFF;
    }

    /** Regular read-only user. */
    public function isRegularUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    /** Kept for backwards-compatibility — same as isRegularUser(). */
    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /** True for both admin and ID staff — anyone who can create/import. */
    public function canCreate(): bool
    {
        return $this->isAdmin() || $this->isIdStaff();
    }

    public function getRoleLabel(): string
    {
        return $this->role?->label() ?? 'Unknown';
    }

    public function getRoleBadgeClass(): string
    {
        return match ($this->role) {
            UserRole::ADMINISTRATOR => 'bg-primary',
            UserRole::ID_STAFF      => 'bg-info text-dark',
            UserRole::USER          => 'bg-secondary',
            default                 => 'bg-secondary',
        };
    }
}
