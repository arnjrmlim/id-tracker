<?php

namespace App\Imports;

use App\Enums\IdStatus;
use App\Models\IdRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class IdRecordImport implements ToCollection, WithHeadingRow
{
    public const REQUIRED_HEADERS = ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN'];

    /**
     * The columns checked when deciding whether a row is completely empty.
     * A row where ALL of these are blank/whitespace is silently skipped.
     */
    private const DATA_COLUMNS = ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN'];

    private string $mode;
    private array  $errors  = [];
    private array  $skippedMessages = [];
    private int    $created = 0;
    private int    $updated = 0;
    private int    $skipped = 0;
    private int    $failed  = 0;
    private int    $total   = 0;  // meaningful (non-blank) rows only

    public function __construct(string $mode = 'both')
    {
        $this->mode = $mode;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeRow($row->toArray());

            // Skip rows that are completely empty across all data columns.
            // These are formatted-but-blank Excel rows and must produce no
            // errors, no counts, and no database operations.
            if ($this->isEmptyRow($normalized)) {
                continue;
            }

            // Only meaningful rows count toward the total.
            $this->total++;
            $rowNum = $index + 2; // +2: row 1 is the header

            try {
                $this->processRow($normalized, $rowNum);
            } catch (\Throwable $e) {
                $this->failed++;
                $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                Log::warning("IdRecordImport error on row {$rowNum}: " . $e->getMessage());
            }
        }
    }

    // ── Row helpers ────────────────────────────────────────────────────────────

    /**
     * Normalise keys to uppercase and trim every string value.
     */
    private function normalizeRow(array $row): array
    {
        $upper = array_change_key_case(
            array_combine(
                array_map('strtoupper', array_keys($row)),
                array_values($row)
            ),
            CASE_UPPER
        );

        // Trim every value so whitespace-only cells are treated as empty.
        foreach ($upper as $key => $value) {
            $upper[$key] = is_string($value) ? trim($value) : $value;
        }

        return $upper;
    }

    /**
     * Returns true when every data column is empty/null/whitespace.
     * Such rows are silently ignored — they are formatted-but-blank Excel rows.
     */
    private function isEmptyRow(array $row): bool
    {
        foreach (self::DATA_COLUMNS as $col) {
            $value = trim((string) ($row[$col] ?? ''));
            if ($value !== '') {
                return false; // at least one column has content → meaningful row
            }
        }
        return true;
    }

    // ── Core row processor ─────────────────────────────────────────────────────

    private function processRow(array $row, int $rowNum): void
    {
        // Values are already trimmed by normalizeRow().
        $name = (string) ($row['NAME'] ?? '');
        $idno = (string) ($row['IDNO'] ?? '');

        // Validate required fields — partial rows must still produce errors.
        if ($name === '') {
            $this->failed++;
            $this->errors[] = "Row {$rowNum}: NAME is required.";
            return;
        }

        if ($idno === '') {
            $this->failed++;
            $this->errors[] = "Row {$rowNum}: IDNO is required.";
            return;
        }

        $dateHired = $this->parseDate($row['DATEH'] ?? null, $rowNum, 'DATEH');
        $birthDate = $this->parseDate($row['BDATE'] ?? null, $rowNum, 'BDATE');

        $imgPath  = ($row['IMG']  ?? '') ?: null;
        $signPath = ($row['SIGN'] ?? '') ?: null;

        $imgSource  = filled($imgPath)  ? IdRecord::SOURCE_NETWORK : null;
        $signSource = filled($signPath) ? IdRecord::SOURCE_NETWORK : null;

        $existing = IdRecord::where('id_number', $idno)->first();

        if ($existing) {
            if ($this->mode === 'add') {
                // Business-rule skip — not a failure.
                $this->skipped++;
                $this->skippedMessages[] = "Row {$rowNum}: Existing IDNO {$idno} skipped.";
                return;
            }

            // UPDATE — never change status, never touch uploaded files.
            $updateData = [
                'name'              => $name,
                'position'          => ($row['POS'] ?? '') ?: null,
                'date_hired'        => $dateHired,
                'birth_date'        => $birthDate,
                'emergency_contact' => ($row['ECON'] ?? '') ?: null,
            ];

            if (filled($imgPath)) {
                $updateData['image_path']        = $imgPath;
                $updateData['image_source']      = IdRecord::SOURCE_NETWORK;
                $updateData['image_upload_path'] = null;
            }
            if (filled($signPath)) {
                $updateData['signature_path']        = $signPath;
                $updateData['signature_source']      = IdRecord::SOURCE_NETWORK;
                $updateData['signature_upload_path'] = null;
            }

            $existing->update($updateData);
            $this->updated++;
        } else {
            if ($this->mode === 'update') {
                $this->skipped++;
                $this->skippedMessages[] = "Row {$rowNum}: New IDNO {$idno} skipped (update-only mode).";
                return;
            }

            IdRecord::create([
                'name'                  => $name,
                'position'              => ($row['POS'] ?? '') ?: null,
                'id_number'             => $idno,
                'date_hired'            => $dateHired,
                'birth_date'            => $birthDate,
                'emergency_contact'     => ($row['ECON'] ?? '') ?: null,
                'image_path'            => $imgPath,
                'image_source'          => $imgSource,
                'image_upload_path'     => null,
                'signature_path'        => $signPath,
                'signature_source'      => $signSource,
                'signature_upload_path' => null,
                'status'                => IdStatus::PENDING->value,
            ]);
            $this->created++;
        }
    }

    // ── Date parser ────────────────────────────────────────────────────────────

    private function parseDate(mixed $value, int $rowNum, string $field): ?string
    {
        $str = trim((string) ($value ?? ''));

        if ($str === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                )->format('Y-m-d');
            } catch (\Throwable) {
                $this->errors[] = "Row {$rowNum}: Could not parse {$field} value '{$value}' (numeric).";
                return null;
            }
        }

        $formats = ['m/d/Y', 'Y-m-d', 'd/m/Y', 'M d, Y', 'm-d-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $str)->format('Y-m-d');
            } catch (\Throwable) {
                // try next format
            }
        }

        try {
            return Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable) {
            $this->errors[] = "Row {$rowNum}: Could not parse {$field} value '{$str}'.";
            return null;
        }
    }

    // ── Result getters ─────────────────────────────────────────────────────────

    public function getErrors(): array           { return $this->errors; }
    public function getSkippedMessages(): array  { return $this->skippedMessages; }
    public function getCreated(): int            { return $this->created; }
    public function getUpdated(): int            { return $this->updated; }
    public function getSkipped(): int            { return $this->skipped; }
    public function getFailed(): int             { return $this->failed; }
    public function getTotal(): int              { return $this->total; }
    public function getSuccessful(): int         { return $this->created + $this->updated; }
}
