<?php

namespace App\Http\Controllers;

use App\Enums\IdStatus;
use App\Http\Requests\BulkChangeStatusRequest;
use App\Http\Requests\ChangeStatusRequest;
use App\Models\IdRecord;
use App\Services\IdStatusService;
use Illuminate\Support\Facades\Gate;

class IdStatusController extends Controller
{
    public function __construct(private readonly IdStatusService $statusService) {}

    /**
     * PATCH /id-records/{id_record}/status
     * Admin-only endpoint — returns 403 for regular users.
     */
    public function update(ChangeStatusRequest $request, IdRecord $idRecord)
    {
        Gate::authorize('changeStatus', $idRecord);

        $history = $this->statusService->changeStatus(
            $idRecord,
            $request->validated('status'),
            auth()->user(),
            $request->validated('remarks'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message'    => 'Status updated successfully.',
                'new_status' => $history->new_status,
                'history_id' => $history->id,
            ]);
        }

        return redirect()->route('id-records.show', $idRecord)
            ->with('success', "Status changed to {$history->new_status}.");
    }

    /**
     * POST /id-records/bulk-status
     * Admin-only bulk update.
     */
    public function bulkUpdate(BulkChangeStatusRequest $request)
    {
        Gate::authorize('bulkChangeStatus', IdRecord::class);

        $result = $this->statusService->bulkChangeStatus(
            $request->validated('ids'),
            $request->validated('status'),
            auth()->user(),
            $request->validated('remarks'),
        );

        return redirect()->route('id-records.index')
            ->with('success', "Bulk update complete: {$result['updated']} records updated.");
    }
}
