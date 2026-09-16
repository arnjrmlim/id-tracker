<?php

namespace App\Http\Controllers;

use App\Exports\IdRecordExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function index()
    {
        $this->authorize('export', \App\Models\IdRecord::class);
        $statuses = \App\Enums\IdStatus::cases();
        return view('exports.index', compact('statuses'));
    }

    /**
     * Export using the original 8-column template format.
     */
    public function exportTemplate(Request $request)
    {
        $this->authorize('export', \App\Models\IdRecord::class);

        $request->validate([
            'status' => ['nullable', 'string'],
            'ids'    => ['nullable', 'array'],
            'ids.*'  => ['integer'],
        ]);

        $export = new IdRecordExport(
            includeStatus: false,
            status: $request->input('status'),
            ids:    $request->input('ids'),
            search: $request->input('search'),
        );

        $filename = 'id_records_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    /**
     * Export with STATUS column — the tracker report.
     */
    public function exportReport(Request $request)
    {
        $this->authorize('export', \App\Models\IdRecord::class);

        $request->validate([
            'status' => ['nullable', 'string'],
            'ids'    => ['nullable', 'array'],
            'ids.*'  => ['integer'],
        ]);

        $export = new IdRecordExport(
            includeStatus: true,
            status: $request->input('status'),
            ids:    $request->input('ids'),
            search: $request->input('search'),
        );

        $filename = 'id_tracker_report_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }
}
