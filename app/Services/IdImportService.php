<?php

namespace App\Services;

use App\Enums\IdStatus;
use App\Imports\IdRecordImport;
use App\Models\IdRecord;
use App\Models\ImportLog;
use App\Models\ImportRequest;
use App\Models\ImportRequestRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IdImportService
{
    // ── Header validation ──────────────────────────────────────────────────────

    public function validateHeaders(UploadedFile $file): ?string
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet       = $spreadsheet->getActiveSheet();
            $headerRow   = $sheet->toArray()[0] ?? [];

            $headers = array_values(array_filter(
                array_map(fn($h) => strtoupper(trim((string) $h)), $headerRow)
            ));

            $missing = array_diff(IdRecordImport::REQUIRED_HEADERS, $headers);

            if (! empty($missing)) {
                return 'Invalid Excel template. Missing headers: ' . implode(', ', $missing)
                    . '. Required: ' . implode(', ', IdRecordImport::REQUIRED_HEADERS);
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Header validation failed: ' . $e->getMessage());
            return 'Could not read the Excel file. Please verify the file is not corrupt.';
        }
    }

    // ── Preview ────────────────────────────────────────────────────────────────

    /**
     * Scan the Excel file and return counts of meaningful rows without writing
     * anything to the database.
     *
     * Uses the same empty-row filter AND the same record-classification logic
     * as parseAndValidateFile() so the preview always matches what the import
     * request will actually store.  For ID Staff (add-only mode), existing
     * records are classified as "skipped" rather than "will update".
     */
    public function preview(UploadedFile $file, User $user): array
    {
        $empty = ['total' => 0, 'new' => 0, 'existing' => 0, 'invalid' => 0, 'skipped' => 0];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray();

            if (count($rows) < 2) {
                return $empty;
            }

            // Determine the effective mode the same way createImportRequest() does
            $mode = $user->isIdStaff() ? 'add' : 'both';

            // Build a map from header name → column index (uppercase, trimmed)
            $headerRow = array_map(fn($h) => strtoupper(trim((string) $h)), $rows[0]);
            $colIndex  = array_flip($headerRow); // e.g. ['NAME' => 0, 'IDNO' => 2, …]

            $total = 0; $new = 0; $existing = 0; $invalid = 0; $skipped = 0;

            for ($i = 1; $i < count($rows); $i++) {
                $raw = $rows[$i];

                // ── Same empty-row filter as IdRecordImport::isEmptyRow() ──
                $isEmpty = true;
                foreach (IdRecordImport::REQUIRED_HEADERS as $col) {
                    $idx = $colIndex[$col] ?? null;
                    if ($idx !== null && trim((string) ($raw[$idx] ?? '')) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if ($isEmpty) {
                    continue; // blank trailing row — silently skip
                }

                $total++;

                $idno = isset($colIndex['IDNO'])
                    ? trim((string) ($raw[$colIndex['IDNO']] ?? ''))
                    : '';
                $name = isset($colIndex['NAME'])
                    ? trim((string) ($raw[$colIndex['NAME']] ?? ''))
                    : '';

                if ($name === '' || $idno === '') {
                    $invalid++;
                    continue;
                }

                // Validate employment type — same check as parseAndValidateFile()
                $rawType = isset($colIndex['EMPLOYMENT TYPE'])
                    ? trim((string) ($raw[$colIndex['EMPLOYMENT TYPE']] ?? ''))
                    : '';
                $typeLower = strtolower($rawType);
                if ($rawType === '' || ($typeLower !== 'employee' && $typeLower !== 'agent')) {
                    $invalid++;
                    continue;
                }

                // Classify using the SAME logic as parseAndValidateFile() so the
                // preview matches what the import request will actually store.
                $record = IdRecord::withTrashed()->where('id_number', $idno)->first();

                if ($record) {
                    $wasTrashed = $record->trashed();
                    if ($mode === 'add' && !$wasTrashed) {
                        // Active record already exists in add-only mode → skip
                        $skipped++;
                    } else {
                        // Trashed record (will be restored + updated) or update mode
                        $existing++;
                    }
                } elseif ($mode === 'update') {
                    // New record in update-only mode → skip
                    $skipped++;
                } else {
                    $new++;
                }
            }

            return compact('total', 'new', 'existing', 'invalid', 'skipped');
        } catch (\Throwable $e) {
            Log::error('Import preview failed: ' . $e->getMessage());
            return $empty;
        }
    }

    // ── Actual import ──────────────────────────────────────────────────────────

    /**
     * Run the import and return a persisted ImportLog.
     *
     * Role enforcement:
     *   Administrator → uses the requested $mode (add / update / both)
     *   ID Staff      → always forced to 'add'; existing IDNOs are skipped
     *   User          → denied at policy level before reaching here
     */
    public function import(UploadedFile $file, User $importedBy, string $mode = 'both'): ImportLog
    {
        if ($importedBy->isIdStaff()) {
            $mode = 'add';
        }

        $importer = new IdRecordImport($mode);
        Excel::import($importer, $file);

        // Merge validation errors and business-rule skip messages for the log.
        $allMessages = array_merge(
            $importer->getErrors(),
            $importer->getSkippedMessages()
        );

        $log = ImportLog::create([
            'filename'        => $file->getClientOriginalName(),
            'total_rows'      => $importer->getTotal(),
            'successful_rows' => $importer->getSuccessful(),
            'created_rows'    => $importer->getCreated(),
            'updated_rows'    => $importer->getUpdated(),
            'skipped_rows'    => $importer->getSkipped(),
            'failed_rows'     => $importer->getFailed(),
            'imported_by'     => $importedBy->id,
            'errors'          => $allMessages ?: null,
        ]);

        Log::info(sprintf(
            'Excel import by %s #%d (role: %s, mode: %s): %d created, %d updated, %d skipped, %d failed.',
            $importedBy->username,
            $importedBy->id,
            $importedBy->getRoleLabel(),
            $mode,
            $importer->getCreated(),
            $importer->getUpdated(),
            $importer->getSkipped(),
            $importer->getFailed()
        ));

        // Store created/updated records for audit trail
        $log->created_records = $importer->getCreatedRecords();
        $log->updated_records = $importer->getUpdatedRecords();
        $log->failed_records = $importer->getFailedRecords();
        $log->skipped_records = $importer->getSkippedRecords();

        return $log;
    }

    // ── Import Request Creation (for ID Staff approval workflow) ───────────────

    /**
     * Validate and parse the Excel file, creating an ImportRequest without
     * actually importing the records. Used for ID Staff approval workflow.
     */
    public function createImportRequest(UploadedFile $file, User $requestedBy, string $mode = 'add'): ImportRequest
    {
        // ID Staff is always forced to 'add' mode
        if ($requestedBy->isIdStaff()) {
            $mode = 'add';
        }

        // Store the file permanently for approval workflow
        $filePath = $file->store('imports/requests', 'local');

        // Parse and validate the file
        $rows = $this->parseAndValidateFile($file, $mode);

        // Create the import request
        $importRequest = ImportRequest::create([
            'requested_by' => $requestedBy->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'status' => 'pending',
            'total_rows' => count($rows),
            'valid_rows' => collect($rows)->where('action', '!=', 'invalid')->count(),
            'invalid_rows' => collect($rows)->where('action', 'invalid')->count(),
            'new_records' => collect($rows)->where('action', 'create')->count(),
            'updates' => collect($rows)->where('action', 'update')->count(),
            'skipped' => collect($rows)->where('action', 'skip')->count(),
            'failed_rows' => collect($rows)->where('action', 'invalid')->count(),
            'errors' => collect($rows)->where('action', 'invalid')->pluck('error_message')->filter()->implode("\n") ?: null,
            'mode' => $mode,
            'validation_results' => [
                'total' => count($rows),
                'new' => collect($rows)->where('action', 'create')->count(),
                'existing' => collect($rows)->where('action', 'update')->count(),
                'invalid' => collect($rows)->where('action', 'invalid')->count(),
            ],
        ]);

        // Store each row for review
        foreach ($rows as $row) {
            ImportRequestRow::create([
                'import_request_id' => $importRequest->id,
                'row_number' => $row['row_number'],
                'name' => $row['name'],
                'id_number' => $row['id_number'],
                'position' => $row['position'],
                'employment_type' => $row['employment_type'],
                'date_hired' => $row['date_hired'],
                'birth_date' => $row['birth_date'],
                'emergency_contact' => $row['emergency_contact'],
                'image_path' => $row['image_path'],
                'signature_path' => $row['signature_path'],
                'action' => $row['action'],
                'error_message' => $row['error_message'] ?? null,
                'raw_data' => $row['raw_data'] ?? null,
            ]);
        }

        Log::info(sprintf(
            'Import request #%d created by %s #%d (role: %s, mode: %s): %d total, %d valid, %d invalid.',
            $importRequest->id,
            $requestedBy->username,
            $requestedBy->id,
            $requestedBy->getRoleLabel(),
            $mode,
            count($rows),
            $importRequest->valid_rows,
            $importRequest->invalid_rows
        ));

        return $importRequest;
    }

    /**
     * Parse and validate the Excel file, returning an array of row data
     * with validation results. Does NOT create any ID Records.
     */
    private function parseAndValidateFile(UploadedFile $file, string $mode): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return [];
            }

            // Build header map
            $headerRow = array_map(fn($h) => strtoupper(trim((string) $h)), $rows[0]);
            $colIndex = array_flip($headerRow);

            $parsedRows = [];
            $normalizer = new NameNormalizationService();

            for ($i = 1; $i < count($rows); $i++) {
                $raw = $rows[$i];
                $rowNum = $i + 1;

                // Skip empty rows
                $isEmpty = true;
                foreach (IdRecordImport::REQUIRED_HEADERS as $col) {
                    $idx = $colIndex[$col] ?? null;
                    if ($idx !== null && trim((string) ($raw[$idx] ?? '')) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if ($isEmpty) {
                    continue;
                }

                // Extract and normalize data
                // Use space versions since we're manually parsing Excel (not using WithHeadingRow)
                $name = isset($colIndex['NAME']) ? trim((string) ($raw[$colIndex['NAME']] ?? '')) : '';
                $idno = isset($colIndex['IDNO']) ? trim((string) ($raw[$colIndex['IDNO']] ?? '')) : '';
                $position = isset($colIndex['POS']) ? trim((string) ($raw[$colIndex['POS']] ?? '')) : '';
                $rawType = isset($colIndex['EMPLOYMENT TYPE']) ? trim((string) ($raw[$colIndex['EMPLOYMENT TYPE']] ?? '')) : '';
                $dateHired = isset($colIndex['DATEH']) ? $raw[$colIndex['DATEH']] : null;
                $birthDate = isset($colIndex['BDATE']) ? $raw[$colIndex['BDATE']] : null;
                $emergencyContact = isset($colIndex['ECON']) ? trim((string) ($raw[$colIndex['ECON']] ?? '')) : '';
                $imgPath = isset($colIndex['IMG']) ? trim((string) ($raw[$colIndex['IMG']] ?? '')) : '';
                $signPath = isset($colIndex['SIGN']) ? trim((string) ($raw[$colIndex['SIGN']] ?? '')) : '';

                // Validate required fields
                if ($name === '' || $idno === '') {
                    $parsedRows[] = [
                        'row_number' => $rowNum,
                        'name' => $name,
                        'id_number' => $idno,
                        'position' => null,
                        'employment_type' => null,
                        'date_hired' => null,
                        'birth_date' => null,
                        'emergency_contact' => null,
                        'image_path' => null,
                        'signature_path' => null,
                        'action' => 'invalid',
                        'error_message' => $name === '' ? "Row {$rowNum}: NAME is required." : "Row {$rowNum}: IDNO is required.",
                        'raw_data' => $raw,
                    ];
                    continue;
                }

                // Normalize employment type
                $employmentType = $this->normalizeEmploymentType($rawType, $rowNum);
                if ($employmentType === false) {
                    $parsedRows[] = [
                        'row_number' => $rowNum,
                        'name' => $name,
                        'id_number' => $idno,
                        'position' => null,
                        'employment_type' => null,
                        'date_hired' => null,
                        'birth_date' => null,
                        'emergency_contact' => null,
                        'image_path' => null,
                        'signature_path' => null,
                        'action' => 'invalid',
                        'error_message' => "Row {$rowNum}: Employment Type must be Employee or Agent. Got: '{$rawType}'.",
                        'raw_data' => $raw,
                    ];
                    continue;
                }

                // Normalize other fields
                $name = $normalizer->normalize($name);
                $position = $normalizer->normalizeTitleCase($position);
                $emergencyContact = $normalizer->normalizeTitleCase($emergencyContact);
                $dateHired = $this->parseDate($dateHired, $rowNum, 'DATEH');
                $birthDate = $this->parseDate($birthDate, $rowNum, 'BDATE');

                // Determine action
                $existing = IdRecord::withTrashed()->where('id_number', $idno)->first();
                $action = 'create';

                if ($existing) {
                    $wasTrashed = $existing->trashed();

                    if ($mode === 'add' && !$wasTrashed) {
                        $action = 'skip';
                    } else {
                        $action = 'update';
                    }
                } elseif ($mode === 'update') {
                    $action = 'skip';
                }

                $parsedRows[] = [
                    'row_number' => $rowNum,
                    'name' => $name,
                    'id_number' => $idno,
                    'position' => $position,
                    'employment_type' => $employmentType,
                    'date_hired' => $dateHired,
                    'birth_date' => $birthDate,
                    'emergency_contact' => $emergencyContact,
                    'image_path' => $imgPath ?: null,
                    'signature_path' => $signPath ?: null,
                    'action' => $action,
                    'error_message' => null,
                    'raw_data' => $raw,
                ];
            }

            return $parsedRows;
        } catch (\Throwable $e) {
            Log::error('Import request parsing failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Execute an approved import request, creating/updating the actual ID Records.
     * Follows the stored action (CREATE/UPDATE) for each row — does NOT re-derive
     * operations from the import mode. Re-checks for conflicts that may have
     * occurred since the request was submitted.
     */
    public function executeApprovedImport(ImportRequest $importRequest, User $approvedBy): ImportLog
    {
        if (!$importRequest->isPending()) {
            throw new \RuntimeException('Import request is not pending approval.');
        }

        if (!$approvedBy->isAdmin()) {
            throw new \RuntimeException('Only administrators can execute import requests.');
        }

        $rows = $importRequest->rows()->where('action', '!=', 'invalid')->where('action', '!=', 'skip')->get();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $createdRecords = [];
        $updatedRecords = [];
        $failedRecords = [];
        $skippedRecords = [];

        // Include the original invalid and skip rows from the request so the
        // audit trail covers every record regardless of result status.
        foreach ($importRequest->rows()->where('action', 'invalid')->get() as $invalidRow) {
            $failedRecords[] = [
                'name' => $invalidRow->name ?: '(unknown)',
                'id_number' => $invalidRow->id_number ?: '(unknown)',
                'error' => $invalidRow->error_message ?: 'Invalid record.',
            ];
        }
        foreach ($importRequest->rows()->where('action', 'skip')->get() as $skipRow) {
            $skippedRecords[] = [
                'name' => $skipRow->name ?: '(unknown)',
                'id_number' => $skipRow->id_number ?: '(unknown)',
                'reason' => 'Skipped during validation.',
            ];
        }

        foreach ($rows as $row) {
            try {
                // Follow the STORED operation — do NOT re-derive from the mode.
                // Re-check for conflicts that may have occurred since submission.
                $existing = IdRecord::withTrashed()->where('id_number', $row->id_number)->first();

                if ($row->action === 'update') {
                    // ── UPDATE: row was validated as an existing record ──────
                    if (!$existing) {
                        // Record was hard-deleted since request submission
                        $skipped++;
                        $errors[] = "Row {$row->row_number}: IDNO {$row->id_number} no longer exists (conflict since request submission).";
                        $skippedRecords[] = [
                            'name' => $row->name ?: '(unknown)',
                            'id_number' => $row->id_number ?: '(unknown)',
                            'reason' => 'Record no longer exists (conflict since request submission).',
                        ];
                        continue;
                    }

                    // Restore soft-deleted record before updating
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    // UPDATE — never change status, never touch uploaded files
                    $updateData = [
                        'name' => $row->name,
                        'position' => $row->position,
                        'employment_type' => $row->employment_type,
                        'date_hired' => $row->date_hired,
                        'birth_date' => $row->birth_date,
                        'emergency_contact' => $row->emergency_contact,
                    ];

                    if (filled($row->image_path)) {
                        $updateData['image_path'] = $row->image_path;
                        $updateData['image_source'] = IdRecord::SOURCE_NETWORK;
                        $updateData['image_upload_path'] = null;
                    }
                    if (filled($row->signature_path)) {
                        $updateData['signature_path'] = $row->signature_path;
                        $updateData['signature_source'] = IdRecord::SOURCE_NETWORK;
                        $updateData['signature_upload_path'] = null;
                    }

                    $existing->update($updateData);
                    $updated++;
                    $updatedRecords[] = [
                        'name' => $row->name,
                        'id_number' => $row->id_number,
                    ];
                } else {
                    // ── CREATE: row was validated as a new record ───────────
                    if ($existing && !$existing->trashed()) {
                        // Record was created since request submission
                        $skipped++;
                        $errors[] = "Row {$row->row_number}: IDNO {$row->id_number} now exists (conflict since request submission).";
                        $skippedRecords[] = [
                            'name' => $row->name ?: '(unknown)',
                            'id_number' => $row->id_number ?: '(unknown)',
                            'reason' => 'Record now exists (conflict since request submission).',
                        ];
                        continue;
                    }

                    if ($existing && $existing->trashed()) {
                        // Record was soft-deleted since submission — restore + update
                        // to avoid a duplicate-key error on insert.
                        $existing->restore();
                        $existing->update([
                            'name' => $row->name,
                            'position' => $row->position,
                            'employment_type' => $row->employment_type,
                            'date_hired' => $row->date_hired,
                            'birth_date' => $row->birth_date,
                            'emergency_contact' => $row->emergency_contact,
                        ]);
                        $updated++;
                        $updatedRecords[] = [
                            'name' => $row->name,
                            'id_number' => $row->id_number,
                        ];
                    } else {
                        // CREATE
                        $record = IdRecord::create([
                            'name' => $row->name,
                            'position' => $row->position,
                            'employment_type' => $row->employment_type,
                            'id_number' => $row->id_number,
                            'date_hired' => $row->date_hired,
                            'birth_date' => $row->birth_date,
                            'emergency_contact' => $row->emergency_contact,
                            'image_path' => $row->image_path,
                            'image_source' => filled($row->image_path) ? IdRecord::SOURCE_NETWORK : null,
                            'image_upload_path' => null,
                            'signature_path' => $row->signature_path,
                            'signature_source' => filled($row->signature_path) ? IdRecord::SOURCE_NETWORK : null,
                            'signature_upload_path' => null,
                            'status' => IdStatus::PENDING->value,
                        ]);
                        $created++;
                        $createdRecords[] = [
                            'name' => $row->name,
                            'id_number' => $row->id_number,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Row {$row->row_number}: " . $e->getMessage();
                $failedRecords[] = [
                    'name' => $row->name ?: '(unknown)',
                    'id_number' => $row->id_number ?: '(unknown)',
                    'error' => $e->getMessage(),
                ];
                Log::warning("Import request execution error on row {$row->row_number}: " . $e->getMessage());
            }
        }

        // Mark request as approved
        $importRequest->update([
            'status' => 'approved',
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);

        // Create import log
        $log = ImportLog::create([
            'filename' => $importRequest->file_name,
            'total_rows' => $importRequest->total_rows,
            'successful_rows' => $created + $updated,
            'created_rows' => $created,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped + $importRequest->skipped,
            'failed_rows' => $failed + $importRequest->invalid_rows,
            'imported_by' => $approvedBy->id,
            'errors' => array_merge($importRequest->errors ? explode("\n", $importRequest->errors) : [], $errors) ?: null,
        ]);

        // Store created/updated records for audit trail
        $log->created_records = $createdRecords;
        $log->updated_records = $updatedRecords;
        $log->failed_records = $failedRecords;
        $log->skipped_records = $skippedRecords;

        // Clean up stored file
        if ($importRequest->file_path) {
            Storage::disk('local')->delete($importRequest->file_path);
        }

        Log::info(sprintf(
            'Import request #%d executed by %s #%d: %d created, %d updated, %d skipped, %d failed.',
            $importRequest->id,
            $approvedBy->username,
            $approvedBy->id,
            $created,
            $updated,
            $skipped,
            $failed
        ));

        return $log;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function normalizeEmploymentType(string $raw, int $rowNum): string|null|false
    {
        if ($raw === '') {
            return false;
        }

        $lower = strtolower($raw);

        if ($lower === 'employee') {
            return 'Employee';
        }

        if ($lower === 'agent') {
            return 'Agent';
        }

        return false;
    }

    private function parseDate(mixed $value, int $rowNum, string $field): ?string
    {
        $str = trim((string) ($value ?? ''));

        if ($str === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \Carbon\Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                )->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $formats = ['m/d/Y', 'Y-m-d', 'd/m/Y', 'M d, Y', 'm-d-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $str)->format('Y-m-d');
            } catch (\Throwable) {
                // try next format
            }
        }

        try {
            return \Carbon\Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
