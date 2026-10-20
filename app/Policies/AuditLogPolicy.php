<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Only active admins can access audit trail
        if ($user->isAdmin() && $user->isActive()) {
            return true;
        }
        return null;
    }

    public function viewAuditTrail(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->isAdmin() && $user->isActive();
    }
}
