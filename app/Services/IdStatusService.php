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
     *
     * $effectiveStatusDate — the date the status should officially take effect.
     *   This is separate from created_at, which always records the actual
     *   moment the system action happened.  Null is fine (old records,
     *   or cases where the effective date equals today).
     */
    public function changeStatus(
        IdRecord $idRecord,
        string   $newStatus,
        User     $changedBy,
        ?string  $remarks              = null,
        ?string  $effectiveStatusDate  = null
    ): IdStatusHistory {
        if (! $changedBy->isAdmin()) {
            abort(403, 'Only administrators can change ID status.');
        }

        if (! $changedBy->isActive()) {
            abort(403, 'Your account is deactivated.');
        }

        $statusEnum = IdStatus::tryFrom($newStatus);
        if ($statusEnum === null) {
            throw ValidationException::withMessages([
                'status' => "Invalid status value: {$newStatus}",
            ]);
        }

        $oldStatus = $idRecord->status;

        return DB::transaction(function () use (
            $idRecord, $statusEnum, $oldStatus,
            $changedBy, $remarks, $effectiveStatusDate
        ) {
            $idRecord->update(['status' => $statusEnum->value]);

            return IdStatusHistory::create([
                'id_record_id'          => $idRecord->id,
                'old_status'            => $oldStatus,
                'new_status'            => $statusEnum->value,
                'changed_by'            => $changedBy->id,
                'remarks'               => $remarks,
                // Stored separately — does NOT overwrite created_at
                'effective_status_date' => $effectiveStatusDate ?: null,
            ]);
        });
    }

    /**
     * Bulk status change. Each record gets its own history entry.
     * The same effective_status_date applies to all records in the batch.
     *
     * @param  int[]  $ids
     * @return array{updated: int, skipped: int}
     */
    public function bulkChangeStatus(
        array   $ids,
        string  $newStatus,
        User    $changedBy,
        ?string $remarks             = null,
        ?string $effectiveStatusDate = null
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

        DB::transaction(function () use (
            $records, $statusEnum, $changedBy,
            $remarks, $effectiveStatusDate, &$updated, &$skipped
        ) {
            foreach ($records as $record) {
                $oldStatus = $record->status;
                $record->update(['status' => $statusEnum->value]);

                IdStatusHistory::create([
                    'id_record_id'          => $record->id,
                    'old_status'            => $oldStatus,
                    'new_status'            => $statusEnum->value,
                    'changed_by'            => $changedBy->id,
                    'remarks'               => $remarks,
                    'effective_status_date' => $effectiveStatusDate ?: null,
                ]);
                $updated++;
            }
        });

        return ['updated' => $updated, 'skipped' => $skipped];
    }
}
