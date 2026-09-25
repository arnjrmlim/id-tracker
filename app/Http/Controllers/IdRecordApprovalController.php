<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveIdRecordRequest;
use App\Http\Requests\RejectIdRecordRequest;
use App\Models\IdRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdRecordApprovalController extends Controller
{
    /**
     * Display pending ID record requests for admin approval.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewPendingRequests', IdRecord::class);

        $query = IdRecord::query()
            ->pendingApproval()
            ->with(['requester', 'statusHistories.changedBy'])
            ->search($request->input('search'))
            ->filterEmploymentType($request->input('employment_type'));

        $sortBy  = $request->input('sort_by', 'requested_at');
        $sortDir = $request->input('sort_dir', 'desc');
        if (in_array($sortBy, ['name', 'position', 'employment_type', 'requested_at'])) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('id-requests.index', compact('requests'));
    }

    /**
     * Display a specific pending request for review.
     */
    public function show(IdRecord $idRecord)
    {
        Gate::authorize('approve', $idRecord);

        $idRecord->load(['requester', 'statusHistories.changedBy']);

        return view('id-requests.show', compact('idRecord'));
    }

    /**
     * Approve a pending ID record request.
     */
    public function approve(ApproveIdRecordRequest $request, IdRecord $idRecord)
    {
        Gate::authorize('approve', $idRecord);

        if (!$idRecord->isPendingApproval()) {
            return redirect()->route('id-requests.index')
                ->with('error', 'This request is not pending approval.');
        }

        $idRecord->update([
            'id_number' => $request->validated('id_number'),
            'request_status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('id-requests.index')
            ->with('success', 'ID record approved successfully.');
    }

    /**
     * Reject a pending ID record request.
     */
    public function reject(RejectIdRecordRequest $request, IdRecord $idRecord)
    {
        Gate::authorize('approve', $idRecord);

        if (!$idRecord->isPendingApproval()) {
            return redirect()->route('id-requests.index')
                ->with('error', 'This request is not pending approval.');
        }

        $idRecord->update([
            'request_status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->validated('rejection_reason'),
        ]);

        return redirect()->route('id-requests.index')
            ->with('success', 'ID record request rejected.');
    }

    /**
     * Display ID Staff's own submitted requests.
     */
    public function myRequests(Request $request)
    {
        Gate::authorize('viewOwnRequests', IdRecord::class);

        $query = IdRecord::query()
            ->byRequester($request->user()->id)
            ->with(['requester', 'approver', 'rejecter'])
            ->search($request->input('search'))
            ->filterEmploymentType($request->input('employment_type'));

        // Filter by request status if provided
        if ($request->filled('request_status')) {
            $query->where('request_status', $request->input('request_status'));
        }

        $sortBy  = $request->input('sort_by', 'requested_at');
        $sortDir = $request->input('sort_dir', 'desc');
        if (in_array($sortBy, ['name', 'position', 'employment_type', 'requested_at', 'request_status'])) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('id-requests.my-requests', compact('requests'));
    }
}
