<?php

namespace App\Services;

use App\Imports\IdRecordImport;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
     * Uses the same empty-row filter as the actual importer so Preview and
     * Import always agree on what constitutes a data row.
     */
    public function preview(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray();

            if (count($rows) < 2) {
                return ['total' => 0, 'new' => 0, 'existing' => 0, 'invalid' => 0];
            }

            // Build a map from header name → column index (uppercase, trimmed)
            $headerRow = array_map(fn($h) => strtoupper(trim((string) $h)), $rows[0]);
            $colIndex  = array_flip($headerRow); // e.g. ['NAME' => 0, 'IDNO' => 2, …]

            $total = 0; $new = 0; $existing = 0; $invalid = 0;

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

                \App\Models\IdRecord::where('id_number', $idno)->exists()
                    ? $existing++
                    : $new++;
            }

            return compact('total', 'new', 'existing', 'invalid');
        } catch (\Throwable $e) {
            Log::error('Import preview failed: ' . $e->getMessage());
            return ['total' => 0, 'new' => 0, 'existing' => 0, 'invalid' => 0];
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

        return $log;
    }
}
