<?php

namespace App\Http\Controllers;

use App\Enums\IdStatus;
use App\Http\Requests\StoreIdRecordRequest;
use App\Http\Requests\UpdateIdRecordRequest;
use App\Models\IdRecord;
use App\Services\AuditLogService;
use App\Services\ImageUploadService;
use App\Services\NameNormalizationService;
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
            ->filterEmploymentType($request->input('employment_type'))
            ->filterDateHiredFrom($request->input('date_from'))
            ->filterDateHiredTo($request->input('date_to'));

        // Hide pending approval requests from main list (they go to pending requests page)
        $query->where(function ($q) {
            $q->whereNull('request_status')
              ->orWhere('request_status', '!=', 'pending');
        });

        $sortBy  = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');
        if (in_array($sortBy, ['name', 'id_number', 'position', 'employment_type', 'date_hired', 'status', 'created_at'])) {
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
        $user = $request->user();

        // Normalize full name
        $data['name'] = (new NameNormalizationService())->normalize($data['name']);

        // Normalize position to title case
        if (isset($data['position'])) {
            $data['position'] = (new NameNormalizationService())->normalizeTitleCase($data['position']);
        }

        // Normalize emergency contact to title case
        if (isset($data['emergency_contact'])) {
            $data['emergency_contact'] = (new NameNormalizationService())->normalizeTitleCase($data['emergency_contact']);
        }

        // Always force PENDING — never trust client-supplied status
        $data['status'] = IdStatus::PENDING->value;

        // Handle approval workflow based on user role
        if ($user->isIdStaff()) {
            // ID Staff creates pending approval request
            $data['request_status'] = 'pending';
            $data['requested_by'] = $user->id;
            $data['requested_at'] = now();
        } else {
            // Admin creates approved record directly
            $data['request_status'] = 'approved';
            $data['approved_by'] = $user->id;
            $data['approved_at'] = now();
        }

        // Handle ID image
        [$data] = $this->processImageInput(
            $data,
            $request,
            'image',
            $request->validated('id_number') ?? ''
        );

        // Handle signature image
        [$data] = $this->processSignatureInput(
            $data,
            $request,
            $request->validated('id_number') ?? ''
        );

        // Remove file upload fields — not DB columns
        unset($data['image_file'], $data['signature_file']);

        $idRecord = IdRecord::create($data);

        // Log audit trail
        if ($user->isIdStaff()) {
            AuditLogService::log(
                action: 'ID_CREATE_REQUEST_SUBMITTED',
                module: 'ID Requests',
                description: "Submitted New ID Record request #{$idRecord->id} for {$idRecord->name}.",
                target: $idRecord,
                newValues: [
                    'name' => $idRecord->name,
                    'id_number' => $idRecord->id_number,
                    'position' => $idRecord->position,
                    'employment_type' => $idRecord->employment_type,
                    'request_status' => 'Pending',
                ]
            );
        } else {
            AuditLogService::log(
                action: 'ID_RECORD_CREATED',
                module: 'ID Records',
                description: "Created ID record #{$idRecord->id_number} for {$idRecord->name}.",
                target: $idRecord,
                newValues: [
                    'name' => $idRecord->name,
                    'id_number' => $idRecord->id_number,
                    'position' => $idRecord->position,
                    'employment_type' => $idRecord->employment_type,
                    'status' => $idRecord->status,
                ]
            );
        }

        $message = $user->isIdStaff()
            ? 'ID record submitted for approval.'
            : 'ID record created successfully.';

        return redirect()->route('id-records.index')
            ->with('success', $message);
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

        $oldValues = [
            'name' => $idRecord->name,
            'position' => $idRecord->position,
            'employment_type' => $idRecord->employment_type,
            'id_number' => $idRecord->id_number,
        ];

        $data = $request->validated();
        unset($data['status']); // status is never changed via this route

        // Normalize full name
        $data['name'] = (new NameNormalizationService())->normalize($data['name']);

        // Normalize position to title case
        if (isset($data['position'])) {
            $data['position'] = (new NameNormalizationService())->normalizeTitleCase($data['position']);
        }

        // Normalize emergency contact to title case
        if (isset($data['emergency_contact'])) {
            $data['emergency_contact'] = (new NameNormalizationService())->normalizeTitleCase($data['emergency_contact']);
        }

        // Handle ID image — may delete old uploaded file if replacing
        [$data] = $this->processImageInput(
            $data,
            $request,
            'image',
            $idRecord->id_number ?? '',
            $idRecord
        );

        // Handle signature image
        [$data] = $this->processSignatureInput(
            $data,
            $request,
            $idRecord->id_number ?? '',
            $idRecord
        );

        unset($data['image_file'], $data['signature_file']);

        $idRecord->update($data);

        $newValues = [
            'name' => $idRecord->name,
            'position' => $idRecord->position,
            'employment_type' => $idRecord->employment_type,
            'id_number' => $idRecord->id_number,
        ];

        // Only log if meaningful fields changed
        if ($oldValues !== $newValues) {
            AuditLogService::log(
                action: 'ID_RECORD_UPDATED',
                module: 'ID Records',
                description: "Updated ID record #{$idRecord->id_number} for {$idRecord->name}.",
                target: $idRecord,
                oldValues: $oldValues,
                newValues: $newValues
            );
        }

        return redirect()->route('id-records.show', $idRecord)
            ->with('success', 'ID record updated successfully.');
    }

    public function destroy(IdRecord $idRecord)
    {
        $this->authorize('delete', $idRecord);

        // Note: we soft-delete so uploaded files are NOT deleted here.
        // Only permanently deleted records would need file cleanup.
        $recordData = [
            'name' => $idRecord->name,
            'id_number' => $idRecord->id_number,
            'position' => $idRecord->position,
        ];

        $idRecord->delete();

        AuditLogService::log(
            action: 'ID_RECORD_DELETED',
            module: 'ID Records',
            description: "Deleted ID record #{$recordData['id_number']} for {$recordData['name']}.",
            target: null,
            oldValues: $recordData,
            newValues: null
        );

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

        AuditLogService::log(
            action: 'ID_RECORD_RESTORED',
            module: 'ID Records',
            description: "Restored ID record #{$record->id_number} for {$record->name}.",
            target: $record,
            newValues: [
                'name' => $record->name,
                'id_number' => $record->id_number,
            ]
        );

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
        ?string   $idNumber,
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
        ?string   $idNumber,
        ?IdRecord $existing = null
    ): array {
        return $this->processImageInput($data, $request, 'signature', $idNumber, $existing);
    }
}
