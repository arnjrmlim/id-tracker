<?php

namespace App\Http\Controllers;

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

        $preview = $this->importService->preview($file);
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

        // Service will also enforce add-only for ID Staff regardless
        $log = $this->importService->import($uploadedFile, $user, $request->input('mode'));

        \Illuminate\Support\Facades\Storage::disk('local')->delete($tmpPath);

        return redirect()->route('imports.result', $log->id);
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
