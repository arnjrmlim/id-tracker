<?php

namespace App\Http\Controllers;

use App\Enums\IdStatus;
use App\Http\Requests\StoreIdRecordRequest;
use App\Http\Requests\UpdateIdRecordRequest;
use App\Models\IdRecord;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class IdRecordController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', IdRecord::class);

        $query = IdRecord::query()
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->filterPosition($request->input('position'))
            ->filterDateHiredFrom($request->input('date_from'))
            ->filterDateHiredTo($request->input('date_to'));

        $sortBy  = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');
        if (in_array($sortBy, ['name', 'id_number', 'position', 'date_hired', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        $records   = $query->paginate(25)->withQueryString();
        $statuses  = IdStatus::cases();
        $positions = IdRecord::select('position')->whereNotNull('position')
                             ->distinct()->orderBy('position')->pluck('position');

        return view('id-records.index', compact('records', 'statuses', 'positions'));
    }

    public function create()
    {
        $this->authorize('create', IdRecord::class);
        return view('id-records.create');
    }

    public function store(StoreIdRecordRequest $request)
    {
        $this->authorize('create', IdRecord::class);

        $data = $request->validated();

        // Always force PENDING — never trust client-supplied status
        $data['status'] = IdStatus::PENDING->value;

        // Handle ID image
        [$data] = $this->processImageInput(
            $data,
            $request,
            'image',
            $request->validated('id_number', '')
        );

        // Handle signature image
        [$data] = $this->processSignatureInput(
            $data,
            $request,
            $request->validated('id_number', '')
        );

        // Remove file upload fields — not DB columns
        unset($data['image_file'], $data['signature_file']);

        IdRecord::create($data);

        return redirect()->route('id-records.index')
            ->with('success', 'ID record created successfully.');
    }

    public function show(IdRecord $idRecord)
    {
        $this->authorize('view', $idRecord);
        $idRecord->load('statusHistories.changedBy');
        return view('id-records.show', compact('idRecord'));
    }

    public function edit(IdRecord $idRecord)
    {
        $this->authorize('update', $idRecord);
        return view('id-records.edit', compact('idRecord'));
    }

    public function update(UpdateIdRecordRequest $request, IdRecord $idRecord)
    {
        $this->authorize('update', $idRecord);

        $data = $request->validated();
        unset($data['status']); // status is never changed via this route

        // Handle ID image — may delete old uploaded file if replacing
        [$data] = $this->processImageInput(
            $data,
            $request,
            'image',
            $idRecord->id_number,
            $idRecord
        );

        // Handle signature image
        [$data] = $this->processSignatureInput(
            $data,
            $request,
            $idRecord->id_number,
            $idRecord
        );

        unset($data['image_file'], $data['signature_file']);

        $idRecord->update($data);

        return redirect()->route('id-records.show', $idRecord)
            ->with('success', 'ID record updated successfully.');
    }

    public function destroy(IdRecord $idRecord)
    {
        $this->authorize('delete', $idRecord);

        // Note: we soft-delete so uploaded files are NOT deleted here.
        // Only permanently deleted records would need file cleanup.
        $idRecord->delete();

        return redirect()->route('id-records.index')
            ->with('success', 'ID record deleted. It can be restored by an administrator.');
    }

    public function trashed(Request $request)
    {
        $this->authorize('restore', IdRecord::class);

        $records = IdRecord::onlyTrashed()
            ->search($request->input('search'))
            ->orderByDesc('deleted_at')
            ->paginate(25)->withQueryString();

        return view('id-records.trashed', compact('records'));
    }

    public function restore(int $id)
    {
        $this->authorize('restore', IdRecord::class);

        $record = IdRecord::onlyTrashed()->findOrFail($id);
        $record->restore();

        return redirect()->route('id-records.trashed')
            ->with('success', 'ID record restored successfully.');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Process image_source, image_file, image_path for either store or update.
     * Returns updated $data array.
     */
    private function processImageInput(
        array     $data,
        Request   $request,
        string    $prefix,        // 'image'
        string    $idNumber,
        ?IdRecord $existing = null
    ): array {
        $source   = $data["{$prefix}_source"] ?? null;
        $fileKey  = "{$prefix}_file";
        $pathKey  = "{$prefix}_path";
        $upKey    = "{$prefix}_upload_path";
        $srcKey   = "{$prefix}_source";

        if ($source === IdRecord::SOURCE_UPLOAD) {
            if ($request->hasFile($fileKey) && $request->file($fileKey)->isValid()) {
                // Delete old uploaded file if replacing
                if ($existing && filled($existing->{$upKey})) {
                    $this->imageService->deleteUploadedFile($existing->{$upKey});
                }
                $stored         = $prefix === 'image'
                    ? $this->imageService->storeIdImage($request->file($fileKey), $idNumber)
                    : $this->imageService->storeSignature($request->file($fileKey), $idNumber);
                $data[$upKey]   = $stored;
                $data[$pathKey] = null; // clear any previous network path
            } else {
                // No new file submitted — keep existing upload path if editing
                $data[$upKey] = $existing?->{$upKey};
            }
        } elseif ($source === IdRecord::SOURCE_NETWORK) {
            // Clear any old uploaded file if switching from upload → network
            if ($existing && $existing->{$srcKey} === IdRecord::SOURCE_UPLOAD && filled($existing->{$upKey})) {
                $this->imageService->deleteUploadedFile($existing->{$upKey});
            }
            $data[$upKey] = null;
        } else {
            // No source selected — clear both
            $data[$pathKey] = null;
            $data[$upKey]   = null;
            $data[$srcKey]  = null;
        }

        return [$data];
    }

    private function processSignatureInput(
        array     $data,
        Request   $request,
        string    $idNumber,
        ?IdRecord $existing = null
    ): array {
        return $this->processImageInput($data, $request, 'signature', $idNumber, $existing);
    }
}
