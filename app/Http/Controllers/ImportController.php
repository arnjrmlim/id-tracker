<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\IdImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImportController extends Controller
{
    public function __construct(private readonly IdImportService $importService) {}

    public function index()
    {
        $this->authorize('import', \App\Models\IdRecord::class);
        return view('imports.index');
    }

    public function preview(Request $request)
    {
        $this->authorize('import', \App\Models\IdRecord::class);

        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $file  = $request->file('excel_file');
        $error = $this->importService->validateHeaders($file);

        if ($error) {
            return back()->withErrors(['excel_file' => $error])->withInput();
        }

        $preview = $this->importService->preview($file, auth()->user());
        $tmpPath = $file->store('imports/tmp', 'local');

        // ID Staff is always locked to add-only mode
        $isIdStaff = auth()->user()->isIdStaff();

        return view('imports.preview', [
            'preview'      => $preview,
            'tmpPath'      => $tmpPath,
            'originalName' => $file->getClientOriginalName(),
            'isIdStaff'    => $isIdStaff,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('import', \App\Models\IdRecord::class);

        $user = auth()->user();

        // ID Staff mode is always 'add' — validate accordingly
        $allowedModes = $user->isIdStaff() ? ['add'] : ['add', 'update', 'both'];

        $request->validate([
            'tmp_path'      => ['required', 'string'],
            'original_name' => ['required', 'string'],
            'mode'          => ['required', \Illuminate\Validation\Rule::in($allowedModes)],
        ]);

        $tmpPath  = $request->input('tmp_path');
        $fullPath = storage_path('app/private/' . $tmpPath);

        if (! file_exists($fullPath)) {
            return back()->withErrors(['tmp_path' => 'Temporary file not found. Please re-upload the file.']);
        }

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            (new \Illuminate\Http\File($fullPath))->getPathname(),
            $request->input('original_name'),
            null,
            null,
            true
        );

        // ID Staff: Create import request for approval
        // Admin: Import directly
        if ($user->isIdStaff()) {
            // Create import request
            $importRequest = $this->importService->createImportRequest($uploadedFile, $user, $request->input('mode'));

            // Delete temporary file (service stores permanent copy)
            \Illuminate\Support\Facades\Storage::disk('local')->delete($tmpPath);

            // Load rows for per-employee audit details
            $importRequest->load('rows');

            // Log audit trail
            AuditLogService::log(
                action: 'IMPORT_REQUEST_SUBMITTED',
                module: 'Import Requests',
                description: "ID Staff {$user->username} submitted Import Request #{$importRequest->id} for approval. File: {$importRequest->file_name}. Valid: {$importRequest->valid_rows}, Invalid: {$importRequest->invalid_rows}.",
                target: $importRequest,
                newValues: array_merge([
                    'file_name' => $importRequest->file_name,
                    'total_rows' => $importRequest->total_rows,
                    'valid_rows' => $importRequest->valid_rows,
                    'invalid_rows' => $importRequest->invalid_rows,
                    'new_records' => $importRequest->new_records,
                    'updates' => $importRequest->updates,
                    'mode' => $importRequest->mode,
                ], $importRequest->getAuditRecords())
            );

            return redirect()->route('import-requests.my-requests')
                ->with('success', "Import request #{$importRequest->id} submitted for Administrator approval.");
        } else {
            // Admin: Import directly
            $log = $this->importService->import($uploadedFile, $user, $request->input('mode'));

            \Illuminate\Support\Facades\Storage::disk('local')->delete($tmpPath);

            // Build description with created/updated records info
            $description = "Imported \"{$request->input('original_name')}\". Mode: {$request->input('mode')}. Created: {$log->created_rows}, Updated: {$log->updated_rows}, Skipped: {$log->skipped_rows}, Failed: {$log->failed_rows}.";

            $newValues = [
                'filename' => $request->input('original_name'),
                'mode' => $request->input('mode'),
                'total_rows' => $log->total_rows,
                'created_rows' => $log->created_rows,
                'updated_rows' => $log->updated_rows,
                'skipped_rows' => $log->skipped_rows,
                'failed_rows' => $log->failed_rows,
            ];

            // Add created/updated records to audit trail (limit to first 50 each to avoid oversized logs)
            if ($log->created_records && count($log->created_records) > 0) {
                $newValues['created_records'] = array_slice($log->created_records, 0, 50);
            }
            if ($log->updated_records && count($log->updated_records) > 0) {
                $newValues['updated_records'] = array_slice($log->updated_records, 0, 50);
            }
            if ($log->failed_records && count($log->failed_records) > 0) {
                $newValues['failed_records'] = array_slice($log->failed_records, 0, 50);
            }
            if ($log->skipped_records && count($log->skipped_records) > 0) {
                $newValues['skipped_records'] = array_slice($log->skipped_records, 0, 50);
            }

            AuditLogService::log(
                action: 'IMPORT_COMPLETED',
                module: 'Import',
                description: $description,
                target: null,
                newValues: $newValues
            );

            return redirect()->route('imports.result', $log->id);
        }
    }

    public function result(int $logId)
    {
        // Both admin and ID Staff can view their own import results
        $this->authorize('import', \App\Models\IdRecord::class);

        $log = \App\Models\ImportLog::with('importedBy')->findOrFail($logId);
        return view('imports.result', compact('log'));
    }

    public function downloadTemplate()
    {
        // Anyone who can import may also download the template
        $this->authorize('import', \App\Models\IdRecord::class);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IdRecordTemplateExport(),
            'id_tracker_template.xlsx'
        );
    }
}
