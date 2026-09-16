<?php

namespace App\Services;

use App\Enums\IdStatus;
use App\Models\IdRecord;
use App\Models\IdStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdStatusService
{
    /**
     * Change the status of a single ID record.
     * Wraps the update + history creation in a transaction.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function changeStatus(
        IdRecord $idRecord,
        string   $newStatus,
        User     $changedBy,
        ?string  $remarks = null
    ): IdStatusHistory {
        // Server-side authorization — never rely on UI alone
        if (! $changedBy->isAdmin()) {
            abort(403, 'Only administrators can change ID status.');
        }

        if (! $changedBy->isActive()) {
            abort(403, 'Your account is deactivated.');
        }

        // Validate the requested status is a known value
        $statusEnum = IdStatus::tryFrom($newStatus);
        if ($statusEnum === null) {
            throw ValidationException::withMessages([
                'status' => "Invalid status value: {$newStatus}",
            ]);
        }

        $oldStatus = $idRecord->status;

        // If same status, still record it (admin may want to add a remark)
        return DB::transaction(function () use ($idRecord, $statusEnum, $oldStatus, $changedBy, $remarks) {
            $idRecord->update(['status' => $statusEnum->value]);

            return IdStatusHistory::create([
                'id_record_id' => $idRecord->id,
                'old_status'   => $oldStatus,
                'new_status'   => $statusEnum->value,
                'changed_by'   => $changedBy->id,
                'remarks'      => $remarks,
            ]);
        });
    }

    /**
     * Bulk status change for multiple ID records.
     * Each record gets its own history entry.
     *
     * @param  int[]   $ids
     * @return array{updated: int, skipped: int}
     */
    public function bulkChangeStatus(
        array   $ids,
        string  $newStatus,
        User    $changedBy,
        ?string $remarks = null
    ): array {
        if (! $changedBy->isAdmin()) {
            abort(403, 'Only administrators can change ID status.');
        }

        $statusEnum = IdStatus::tryFrom($newStatus);
        if ($statusEnum === null) {
            throw ValidationException::withMessages([
                'status' => "Invalid status value: {$newStatus}",
            ]);
        }

        $records = IdRecord::whereIn('id', $ids)->get();
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($records, $statusEnum, $changedBy, $remarks, &$updated, &$skipped) {
            foreach ($records as $record) {
                $oldStatus = $record->status;
                $record->update(['status' => $statusEnum->value]);

                IdStatusHistory::create([
                    'id_record_id' => $record->id,
                    'old_status'   => $oldStatus,
                    'new_status'   => $statusEnum->value,
                    'changed_by'   => $changedBy->id,
                    'remarks'      => $remarks,
                ]);
                $updated++;
            }
        });

        return ['updated' => $updated, 'skipped' => $skipped];
    }
}
