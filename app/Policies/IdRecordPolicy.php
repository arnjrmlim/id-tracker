<?php

namespace App\Policies;

use App\Models\IdRecord;
use App\Models\User;

class IdRecordPolicy
{
    /**
     * Administrators bypass all checks — they have full access.
     * Inactive users are always denied before reaching any method.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isActive()) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        return null; // defer to individual methods
    }

    // ── Read ───────────────────────────────────────────────────────────────────

    /** All active roles can view the list. */
    public function viewAny(User $user): bool
    {
        return true; // before() already handles inactive
    }

    /** All active roles can view a single record. */
    public function view(User $user, IdRecord $idRecord): bool
    {
        return true;
    }

    // ── Write ──────────────────────────────────────────────────────────────────

    /**
     * CREATE: Administrator + ID Staff.
     * Regular User cannot create.
     */
    public function create(User $user): bool
    {
        return $user->isIdStaff();
    }

    /**
     * UPDATE: Administrator only.
     * ID Staff explicitly denied — returns false regardless of UI state.
     */
    public function update(User $user, IdRecord $idRecord): bool
    {
        return false; // admin-only via before()
    }

    /**
     * DELETE: Administrator only.
     */
    public function delete(User $user, IdRecord $idRecord): bool
    {
        return false; // admin-only via before()
    }

    /**
     * RESTORE: Administrator only.
     */
    public function restore(User $user, IdRecord $idRecord): bool
    {
        return false; // admin-only via before()
    }

    // ── Status ─────────────────────────────────────────────────────────────────

    /**
     * CHANGE STATUS: Administrator only.
     * ID Staff and User are both denied.
     */
    public function changeStatus(User $user, IdRecord $idRecord): bool
    {
        return false; // admin-only via before()
    }

    /**
     * BULK CHANGE STATUS: Administrator only.
     */
    public function bulkChangeStatus(User $user): bool
    {
        return false; // admin-only via before()
    }

    // ── Import / Export ────────────────────────────────────────────────────────

    /**
     * IMPORT: Administrator + ID Staff.
     * Note: import *behavior* differs per role (enforced in IdImportService).
     */
    public function import(User $user): bool
    {
        return $user->isIdStaff();
    }

    /**
     * EXPORT: Administrator + ID Staff.
     * Regular User cannot export.
     */
    public function export(User $user): bool
    {
        return $user->isIdStaff();
    }
}
