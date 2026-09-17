<?php

namespace App\Imports;

use App\Enums\IdStatus;
use App\Models\IdRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class IdRecordImport implements ToCollection, WithHeadingRow
{
    public const REQUIRED_HEADERS = ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN'];

    /**
     * The columns checked when deciding whether a row is completely empty.
     */
    private const DATA_COLUMNS = ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN'];

    /** Allowed MIME types for imported image files. */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'];

    private string $mode;
    private array  $errors          = [];
    private array  $skippedMessages = [];
    private int    $created         = 0;
    private int    $updated         = 0;
    private int    $skipped         = 0;
    private int    $failed          = 0;
    private int    $total           = 0;

    public function __construct(string $mode = 'both')
    {
        $this->mode = $mode;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeRow($row->toArray());

            if ($this->isEmptyRow($normalized)) {
                continue;
            }

            $this->total++;
            $rowNum = $index + 2;

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

    private function normalizeRow(array $row): array
    {
        $upper = array_change_key_case(
            array_combine(
                array_map('strtoupper', array_keys($row)),
                array_values($row)
            ),
            CASE_UPPER
        );

        foreach ($upper as $key => $value) {
            $upper[$key] = is_string($value) ? trim($value) : $value;
        }

        return $upper;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach (self::DATA_COLUMNS as $col) {
            if (trim((string) ($row[$col] ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }

    // ── Core row processor ─────────────────────────────────────────────────────

    private function processRow(array $row, int $rowNum): void
    {
        $name = (string) ($row['NAME'] ?? '');
        $idno = (string) ($row['IDNO'] ?? '');

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

        $rawImg  = ($row['IMG']  ?? '') ?: null;
        $rawSign = ($row['SIGN'] ?? '') ?: null;

        // ── Resolve image source ───────────────────────────────────────────────
        // If the path references a file that is accessible from the server right
        // now (e.g. the import is being run on the server PC or via a mapped
        // network share the server process can read), copy the file into Laravel
        // storage and save the relative path.  This makes the image immediately
        // accessible to any LAN client through the web application.
        //
        // If the path is not accessible (client-local path, inaccessible share,
        // etc.), fall back to storing it as a 'network' reference so the path is
        // preserved and can be resolved later.

        [$imgSource, $imgPath, $imgUploadPath] = $this->resolveImageField(
            $rawImg, $idno, 'id-images', ''
        );

        [$signSource, $signPath, $signUploadPath] = $this->resolveImageField(
            $rawSign, $idno, 'signatures', '_signature'
        );

        $existing = IdRecord::where('id_number', $idno)->first();

        if ($existing) {
            if ($this->mode === 'add') {
                $this->skipped++;
                $this->skippedMessages[] = "Row {$rowNum}: Existing IDNO {$idno} skipped.";
                return;
            }

            // UPDATE — never change status, never remove an existing upload
            // unless the new import row explicitly provides a replacement.
            $updateData = [
                'name'              => $name,
                'position'          => ($row['POS'] ?? '') ?: null,
                'date_hired'        => $dateHired,
                'birth_date'        => $birthDate,
                'emergency_contact' => ($row['ECON'] ?? '') ?: null,
            ];

            if ($rawImg !== null) {
                $updateData['image_path']        = $imgPath;
                $updateData['image_source']       = $imgSource;
                $updateData['image_upload_path']  = $imgUploadPath;
            }

            if ($rawSign !== null) {
                $updateData['signature_path']        = $signPath;
                $updateData['signature_source']       = $signSource;
                $updateData['signature_upload_path']  = $signUploadPath;
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
                'name'                   => $name,
                'position'               => ($row['POS'] ?? '') ?: null,
                'id_number'              => $idno,
                'date_hired'             => $dateHired,
                'birth_date'             => $birthDate,
                'emergency_contact'      => ($row['ECON'] ?? '') ?: null,
                'image_path'             => $imgPath,
                'image_source'           => $imgSource,
                'image_upload_path'      => $imgUploadPath,
                'signature_path'         => $signPath,
                'signature_source'       => $signSource,
                'signature_upload_path'  => $signUploadPath,
                'status'                 => IdStatus::PENDING->value,
            ]);
            $this->created++;
        }
    }

    // ── Image field resolver ───────────────────────────────────────────────────

    /**
     * Determine the best storage strategy for an IMG/SIGN path from Excel.
     *
     * Strategy:
     *   1. If the raw path is empty  → null source, no paths stored.
     *   2. If the file is accessible from the server → copy into Laravel public
     *      storage, return source='upload', relative upload path, image_path=null.
     *   3. If the file is not accessible (client-local / inaccessible share)
     *      → store as source='network', preserve raw path for future reference.
     *
     * Returns: [source, image_path, image_upload_path]
     */
    private function resolveImageField(
        ?string $rawPath,
        string  $idno,
        string  $folder,
        string  $suffix
    ): array {
        if (empty($rawPath)) {
            return [null, null, null];
        }

        // Normalise separators for Windows
        $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, $rawPath);

        // Attempt to read the file from the server's filesystem
        if (
            file_exists($normalizedPath)
            && is_readable($normalizedPath)
            && is_file($normalizedPath)
        ) {
            // Validate file size (max 20 MB for imports)
            $size = filesize($normalizedPath);
            if ($size === false || $size > 20 * 1024 * 1024) {
                Log::warning("IdRecordImport: image too large, storing as network reference: {$rawPath}");
                return [IdRecord::SOURCE_NETWORK, $rawPath, null];
            }

            // Validate MIME type
            $mime = @mime_content_type($normalizedPath);
            if ($mime === false || ! in_array($mime, self::ALLOWED_MIME, true)) {
                Log::warning("IdRecordImport: unsupported MIME ({$mime}), storing as network reference: {$rawPath}");
                return [IdRecord::SOURCE_NETWORK, $rawPath, null];
            }

            // Generate safe filename and copy into public storage
            $ext        = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION)) ?: 'png';
            $safeId     = preg_replace('/[^a-zA-Z0-9_-]/', '_', $idno);
            $random     = Str::random(6);
            $filename   = "{$safeId}{$suffix}_{$random}.{$ext}";
            $subDir     = now()->format('Y/m');
            $storagePath = "{$folder}/{$subDir}/{$filename}";

            try {
                $contents = file_get_contents($normalizedPath);
                if ($contents === false) {
                    throw new \RuntimeException('file_get_contents returned false');
                }
                Storage::disk('public')->put($storagePath, $contents);
                Log::info("IdRecordImport: copied image to storage: {$storagePath}");
                return [IdRecord::SOURCE_UPLOAD, null, $storagePath];
            } catch (\Throwable $e) {
                Log::warning("IdRecordImport: failed to copy image ({$e->getMessage()}), storing as network reference: {$rawPath}");
                return [IdRecord::SOURCE_NETWORK, $rawPath, null];
            }
        }

        // File not accessible from server — store path as a network reference
        return [IdRecord::SOURCE_NETWORK, $rawPath, null];
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
