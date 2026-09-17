<?php

namespace App\Http\Controllers;

use App\Models\IdRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BulkImageDownloadController extends Controller
{
    /** Accepted MIME types for images. */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
    ];

    /** Canonical extension per MIME type. */
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/bmp'  => 'bmp',
    ];

    // ── Public endpoints ───────────────────────────────────────────────────────

    /**
     * Bulk-download the *uploaded* (profile) images for selected records.
     * ZIP filename: ID_Records_Uploaded_Images_YYYY-MM-DD.zip
     */
    public function download(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('downloadImages', IdRecord::class);

        return $this->buildZip(
            request:       $request,
            imageType:     'upload',
            zipLabel:      'Uploaded_Images',
            noImagesMsg:   'None of the selected records have an uploaded image available.',
        );
    }

    /**
     * Bulk-download the *signature* images for selected records.
     * ZIP filename: ID_Records_Signature_Images_YYYY-MM-DD.zip
     */
    public function downloadSignatures(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('downloadSignatureImages', IdRecord::class);

        return $this->buildZip(
            request:       $request,
            imageType:     'signature',
            zipLabel:      'Signature_Images',
            noImagesMsg:   'None of the selected records have a signature image available.',
        );
    }

    // ── Shared ZIP builder ─────────────────────────────────────────────────────

    /**
     * Core implementation used by both public endpoints.
     *
     * @param  string $imageType   'upload' or 'signature'
     * @param  string $zipLabel    Used in the ZIP filename, e.g. 'Uploaded_Images'
     * @param  string $noImagesMsg Error message when the resulting ZIP would be empty
     */
    private function buildZip(
        Request $request,
        string  $imageType,
        string  $zipLabel,
        string  $noImagesMsg,
    ): StreamedResponse|\Illuminate\Http\JsonResponse {

        // ── 1. Validate input ─────────────────────────────────────────────────
        $request->validate([
            'ids'        => ['required_without:select_all', 'nullable', 'array', 'min:1'],
            'ids.*'      => ['integer', 'min:1'],
            'select_all' => ['nullable', 'boolean'],
            'search'     => ['nullable', 'string', 'max:255'],
            'status'     => ['nullable', 'string', 'max:100'],
            'position'   => ['nullable', 'string', 'max:255'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
        ]);

        // ── 2. Resolve authoritative record IDs ───────────────────────────────
        if ($request->boolean('select_all')) {
            $ids = IdRecord::query()
                ->search($request->input('search'))
                ->filterStatus($request->input('status'))
                ->filterPosition($request->input('position'))
                ->filterDateHiredFrom($request->input('date_from'))
                ->filterDateHiredTo($request->input('date_to'))
                ->pluck('id')
                ->all();
        } else {
            $requested = array_map('intval', $request->input('ids', []));
            $ids       = IdRecord::whereIn('id', $requested)->pluck('id')->all();
        }

        if (empty($ids)) {
            return response()->json([
                'message' => 'No matching records found for the requested selection.',
            ], 422);
        }

        // ── 3. Fetch only the columns needed for this image type ──────────────
        $columns = match ($imageType) {
            'signature' => ['id', 'name', 'id_number', 'signature_source', 'signature_path', 'signature_upload_path'],
            default     => ['id', 'name', 'id_number', 'image_source',     'image_path',     'image_upload_path'],
        };

        /** @var IdRecord[] $records */
        $records = IdRecord::whereIn('id', $ids)
            ->select($columns)
            ->orderBy('name')
            ->get();

        // ── 4. Build ZIP into a temp file ─────────────────────────────────────
        $tmpPath = tempnam(sys_get_temp_dir(), 'id_zip_');
        if ($tmpPath === false) {
            return response()->json(['message' => 'Could not create a temporary file on the server.'], 500);
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpPath);
            return response()->json(['message' => 'Could not open ZIP archive.'], 500);
        }

        $included      = 0;
        $skipped       = 0;
        $usedFilenames = [];

        foreach ($records as $record) {
            [$resolvedPath, $isManagedStorage] = $this->resolveImagePath($record, $imageType);

            if ($resolvedPath === null) {
                $skipped++;
                continue;
            }

            $fullPath = $isManagedStorage
                ? Storage::disk('public')->path($resolvedPath)
                : str_replace('/', DIRECTORY_SEPARATOR, $resolvedPath);

            if (! file_exists($fullPath) || ! is_readable($fullPath)) {
                $skipped++;
                continue;
            }

            $mime = @mime_content_type($fullPath);
            if ($mime === false || ! in_array($mime, self::ALLOWED_MIME, true)) {
                $skipped++;
                continue;
            }

            $ext      = self::MIME_EXT[$mime] ?? pathinfo($fullPath, PATHINFO_EXTENSION) ?: 'jpg';
            $basename = $this->buildFilename($record->name, $record->id_number, $ext, $usedFilenames);

            // Add directly from disk — no binary loading into memory
            $zip->addFile($fullPath, $basename);
            $included++;
        }

        $zip->close();

        if ($included === 0) {
            @unlink($tmpPath);
            return response()->json(['message' => $noImagesMsg], 422);
        }

        // ── 5. Write ZIP comment (transparency) then stream ───────────────────
        $comment = sprintf(
            "Generated: %s\nSelected records: %d\nImages included: %d\nRecords without images: %d",
            now()->toDateTimeString(),
            count($records),
            $included,
            $skipped
        );
        $reopen = new ZipArchive();
        if ($reopen->open($tmpPath) === true) {
            $reopen->setArchiveComment($comment);
            $reopen->close();
        }

        $zipFilename = 'ID_Records_' . $zipLabel . '_' . now()->format('Y-m-d') . '.zip';
        $zipSize     = filesize($tmpPath);

        return response()->stream(
            function () use ($tmpPath) {
                $handle = fopen($tmpPath, 'rb');
                if ($handle) {
                    fpassthru($handle);
                    fclose($handle);
                }
                @unlink($tmpPath);
            },
            200,
            [
                'Content-Type'        => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $zipFilename . '"',
                'Content-Length'      => $zipSize,
                'Cache-Control'       => 'no-store, no-cache, must-revalidate',
                'Pragma'              => 'no-cache',
                'X-Images-Included'  => $included,
                'X-Images-Skipped'   => $skipped,
                'X-Total-Selected'   => count($records),
            ]
        );
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve the storage path for the requested image type on a record.
     *
     * Returns [path, isManagedStorage].
     * Returns [null, false] when no image is available or safe to bundle.
     *
     * Only SOURCE_UPLOAD images are bundled — network/remote paths are not
     * proxied into the ZIP to prevent arbitrary filesystem exposure.
     */
    private function resolveImagePath(IdRecord $record, string $imageType): array
    {
        if ($imageType === 'signature') {
            if ($record->signature_source === IdRecord::SOURCE_UPLOAD
                && filled($record->signature_upload_path)) {
                return [$record->signature_upload_path, true];
            }
            return [null, false];
        }

        // 'upload' (default — uploaded profile image)
        if ($record->image_source === IdRecord::SOURCE_UPLOAD
            && filled($record->image_upload_path)) {
            return [$record->image_upload_path, true];
        }
        return [null, false];
    }

    /**
     * Build a safe, human-readable, unique filename for the ZIP entry.
     *
     * Collision handling: first occurrence → NAME.ext,
     * subsequent occurrences → NAME_2.ext, NAME_3.ext, …
     *
     * @param  array<string,int> $used  Pass-by-reference collision tracker.
     */
    private function buildFilename(
        string $name,
        string $idNumber,
        string $ext,
        array  &$used
    ): string {
        $sanitized = $this->sanitizeName($name);

        if ($sanitized === '') {
            $sanitized = $this->sanitizeName($idNumber) ?: 'RECORD';
        }

        $ext       = strtolower(ltrim($ext, '.'));
        $candidate = "{$sanitized}.{$ext}";

        if (! isset($used[$candidate])) {
            $used[$candidate] = 1;
            return $candidate;
        }

        $used[$candidate]++;
        return "{$sanitized}_{$used[$candidate]}.{$ext}";
    }

    /**
     * Sanitize a person's name into a Windows-safe, upper-case,
     * underscore-separated filename stem.
     */
    private function sanitizeName(string $name): string
    {
        $name = mb_strtoupper(trim($name));
        // Strip Windows-illegal characters
        $name = preg_replace('/[\\\\\/:\*\?"<>|]/', ' ', $name);
        // Strip anything that's not a Unicode letter, digit, or space
        $name = preg_replace('/[^\p{L}\p{N} ]/u', ' ', $name);
        // Collapse spaces → single underscore
        $name = preg_replace('/\s+/', '_', trim($name));
        return trim($name, '_');
    }
}
