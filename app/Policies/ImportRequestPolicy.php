<?php

namespace App\Policies;

use App\Models\ImportRequest;
use App\Models\User;

class ImportRequestPolicy
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

    /**
     * VIEW PENDING REQUESTS: Administrator only.
     * Admin can view all pending import requests.
     */
    public function viewPendingImportRequests(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * VIEW REQUEST: Administrator or requester.
     * Admin can view any request, ID Staff can view their own.
     */
    public function viewImportRequest(User $user, ImportRequest $importRequest): bool
    {
        return $user->isAdmin() || $importRequest->requested_by === $user->id;
    }

    /**
     * APPROVE/REJECT: Administrator only.
     * Only admins can approve or reject import requests.
     */
    public function approveImportRequest(User $user, ImportRequest $importRequest): bool
    {
        return $user->isAdmin();
    }

    /**
     * VIEW OWN REQUESTS: ID Staff only.
     * ID Staff can view their own submitted import requests.
     */
    public function viewOwnImportRequests(User $user): bool
    {
        return $user->isIdStaff();
    }
}
