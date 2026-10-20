<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectImportRequestRequest;
use App\Models\ImportRequest;
use App\Services\AuditLogService;
use App\Services\IdImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImportRequestController extends Controller
{
    public function __construct(private readonly IdImportService $importService) {}

    /**
     * Display pending import requests for admin approval.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewPendingImportRequests', ImportRequest::class);

        $query = ImportRequest::with(['requester', 'rows'])
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('import-requests.index', compact('requests'));
    }

    /**
     * Display a specific import request for review.
     */
    public function show(ImportRequest $importRequest)
    {
        Gate::authorize('viewImportRequest', $importRequest);

        $importRequest->load(['requester', 'rows']);

        return view('import-requests.show', compact('importRequest'));
    }

    /**
     * Approve a pending import request and execute the import.
     */
    public function approve(Request $request, ImportRequest $importRequest)
    {
        Gate::authorize('approveImportRequest', $importRequest);

        if (!$importRequest->isPending()) {
            return redirect()->route('import-requests.index')
                ->with('error', 'This import request is not pending approval.');
        }

        $user = auth()->user();

        try {
            // Execute the approved import
            $log = $this->importService->executeApprovedImport($importRequest, $user);

            // Log audit trail
            AuditLogService::log(
                action: 'IMPORT_REQUEST_APPROVED',
                module: 'Import Requests',
                description: "Admin {$user->username} approved Import Request #{$importRequest->id} submitted by {$importRequest->requester->username}. File: {$importRequest->file_name}. Created: {$log->created_rows}, Updated: {$log->updated_rows}.",
                target: $importRequest,
                oldValues: [
                    'status' => 'Pending',
                ],
                newValues: array_merge([
                    'status' => 'Approved',
                    'approved_by' => $user->username,
                    'created_rows' => $log->created_rows,
                    'updated_rows' => $log->updated_rows,
                ], [
                    'created_records' => array_slice($log->created_records ?? [], 0, 50),
                    'updated_records' => array_slice($log->updated_records ?? [], 0, 50),
                    'failed_records'  => array_slice($log->failed_records ?? [], 0, 50),
                    'skipped_records' => array_slice($log->skipped_records ?? [], 0, 50),
                ])
            );

            return redirect()->route('imports.result', $log->id)
                ->with('success', "Import request #{$importRequest->id} approved and executed successfully.");
        } catch (\Throwable $e) {
            return redirect()->route('import-requests.show', $importRequest)
                ->with('error', 'Failed to execute import: ' . $e->getMessage());
        }
    }

    /**
     * Reject a pending import request.
     */
    public function reject(RejectImportRequestRequest $request, ImportRequest $importRequest)
    {
        Gate::authorize('approveImportRequest', $importRequest);

        if (!$importRequest->isPending()) {
            return redirect()->route('import-requests.index')
                ->with('error', 'This import request is not pending approval.');
        }

        $user = auth()->user();

        $importRequest->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->validated('rejection_reason'),
        ]);

        // Clean up stored file
        if ($importRequest->file_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($importRequest->file_path);
        }

        // Load rows for per-employee audit details
        $importRequest->load('rows');

        // Log audit trail
        AuditLogService::log(
            action: 'IMPORT_REQUEST_REJECTED',
            module: 'Import Requests',
            description: "Admin {$user->username} rejected Import Request #{$importRequest->id} submitted by {$importRequest->requester->username}. Reason: {$request->validated('rejection_reason')}",
            target: $importRequest,
            oldValues: [
                'status' => 'Pending',
            ],
            newValues: array_merge([
                'status' => 'Rejected',
                'rejection_reason' => $request->validated('rejection_reason'),
            ], $importRequest->getAuditRecords())
        );

        return redirect()->route('import-requests.index')
            ->with('success', 'Import request rejected.');
    }

    /**
     * Display ID Staff's own submitted import requests.
     */
    public function myRequests(Request $request)
    {
        Gate::authorize('viewOwnImportRequests', ImportRequest::class);

        $query = ImportRequest::with(['requester', 'approver', 'rejecter'])
            ->byRequester(auth()->id())
            ->orderByDesc('created_at');

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('import-requests.my-requests', compact('requests'));
    }
}
