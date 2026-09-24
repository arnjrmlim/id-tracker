<?php

namespace App\Http\Controllers;

use App\Exports\IdRecordExport;
use App\Models\IdRecord;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function index()
    {
        $this->authorize('export', IdRecord::class);
        $statuses = \App\Enums\IdStatus::cases();
        return view('exports.index', compact('statuses'));
    }

    /**
     * Export using the original 8-column template format.
     *
     * Supports two calling modes:
     *   A) From the ID Records index table (POST with ids[], select_all, search, status, …)
     *   B) From the standalone export page (POST with status, search only — no ids[])
     */
    public function exportTemplate(Request $request)
    {
        $this->authorize('export', IdRecord::class);

        $request->validate([
            'ids'             => ['nullable', 'array'],
            'ids.*'           => ['integer', 'min:1'],
            'select_all'      => ['nullable', 'boolean'],
            'status'          => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', 'string', 'max:20'],
            'search'          => ['nullable', 'string', 'max:255'],
            'position'        => ['nullable', 'string', 'max:255'],
            'date_from'       => ['nullable', 'date'],
            'date_to'         => ['nullable', 'date'],
        ]);

        $ids = $this->resolveIds($request);

        // Guard: ids array present but resolved to nothing means no selection
        if ($this->selectionWasRequested($request) && empty($ids)) {
            return back()->with('error', 'Please select at least one record to export.');
        }

        $export = new IdRecordExport(
            includeStatus:  false,
            status:         $ids ? null : $request->input('status'),
            employmentType: $ids ? null : $request->input('employment_type'),
            ids:            $ids ?: null,
            search:         $ids ? null : $request->input('search'),
        );

        $filename = 'id_records_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    /**
     * Export with STATUS column — the tracker report.
     */
    public function exportReport(Request $request)
    {
        $this->authorize('export', IdRecord::class);

        $request->validate([
            'ids'             => ['nullable', 'array'],
            'ids.*'           => ['integer', 'min:1'],
            'select_all'      => ['nullable', 'boolean'],
            'status'          => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', 'string', 'max:20'],
            'search'          => ['nullable', 'string', 'max:255'],
            'position'        => ['nullable', 'string', 'max:255'],
            'date_from'       => ['nullable', 'date'],
            'date_to'         => ['nullable', 'date'],
        ]);

        $ids = $this->resolveIds($request);

        if ($this->selectionWasRequested($request) && empty($ids)) {
            return back()->with('error', 'Please select at least one record to export.');
        }

        $export = new IdRecordExport(
            includeStatus:  true,
            status:         $ids ? null : $request->input('status'),
            employmentType: $ids ? null : $request->input('employment_type'),
            ids:            $ids ?: null,
            search:         $ids ? null : $request->input('search'),
        );

        $filename = 'id_tracker_report_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve the authoritative list of record IDs for the export.
     *
     * select_all=1  → re-apply index filters and return all matching IDs.
     * ids[] present → validate against DB and return only those that exist.
     * neither       → return [] (standalone export page: no ID filtering).
     *
     * @return int[]
     */
    private function resolveIds(Request $request): array
    {
        if ($request->boolean('select_all')) {
            return IdRecord::query()
                ->search($request->input('search'))
                ->filterStatus($request->input('status'))
                ->filterEmploymentType($request->input('employment_type'))
                ->filterPosition($request->input('position'))
                ->filterDateHiredFrom($request->input('date_from'))
                ->filterDateHiredTo($request->input('date_to'))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($request->filled('ids')) {
            $requested = array_map('intval', $request->input('ids', []));
            return IdRecord::whereIn('id', $requested)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    /**
     * Returns true when the caller explicitly submitted a selection
     * (select_all flag OR an ids array), meaning we must guard against
     * an empty result.  Standalone export page submits neither.
     */
    private function selectionWasRequested(Request $request): bool
    {
        return $request->boolean('select_all') || $request->filled('ids');
    }
}
