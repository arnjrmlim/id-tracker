<?php

namespace App\Http\Controllers;

use App\Models\IdRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdRecordImageController extends Controller
{
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
    ];

    // ── Public routes ──────────────────────────────────────────────────────────

    public function image(IdRecord $idRecord): StreamedResponse|\Illuminate\Http\Response
    {
        Gate::authorize('view', $idRecord);

        if ($idRecord->image_source === IdRecord::SOURCE_UPLOAD) {
            return $this->serveStoredFile($idRecord->image_upload_path, 'ID image not available.');
        }

        return $this->streamNetworkFile($idRecord->image_path, 'ID image not available.');
    }

    public function signature(IdRecord $idRecord): StreamedResponse|\Illuminate\Http\Response
    {
        Gate::authorize('view', $idRecord);

        if ($idRecord->signature_source === IdRecord::SOURCE_UPLOAD) {
            return $this->serveStoredFile($idRecord->signature_upload_path, 'Signature image not available.');
        }

        return $this->streamNetworkFile($idRecord->signature_path, 'Signature image not available.');
    }

    // ── Private: serve uploaded file from Laravel public storage ──────────────

    private function serveStoredFile(?string $storagePath, string $notFoundMessage): StreamedResponse|\Illuminate\Http\Response
    {
        if (blank($storagePath) || ! Storage::disk('public')->exists($storagePath)) {
            return $this->unavailableResponse($notFoundMessage);
        }

        $fullPath = Storage::disk('public')->path($storagePath);
        $mime     = mime_content_type($fullPath);

        if ($mime === false || ! in_array($mime, self::ALLOWED_MIME, true)) {
            return $this->unavailableResponse($notFoundMessage);
        }

        $size = Storage::disk('public')->size($storagePath);

        return response()->stream(function () use ($fullPath) {
            $handle = fopen($fullPath, 'rb');
            if ($handle) {
                fpassthru($handle);
                fclose($handle);
            }
        }, 200, [
            'Content-Type'  => $mime,
            'Content-Length'=> $size,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    // ── Private: stream a network/local filesystem path ────────────────────────

    private function streamNetworkFile(?string $path, string $notFoundMessage): StreamedResponse|\Illuminate\Http\Response
    {
        if (blank($path)) {
            return $this->unavailableResponse($notFoundMessage);
        }

        $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (! file_exists($normalizedPath) || ! is_readable($normalizedPath)) {
            return $this->unavailableResponse($notFoundMessage);
        }

        $size = filesize($normalizedPath);
        if ($size === false || $size > 20 * 1024 * 1024) {
            return $this->unavailableResponse($notFoundMessage);
        }

        $mime = mime_content_type($normalizedPath);
        if ($mime === false || ! in_array($mime, self::ALLOWED_MIME, true)) {
            return $this->unavailableResponse($notFoundMessage);
        }

        return response()->stream(function () use ($normalizedPath) {
            $handle = fopen($normalizedPath, 'rb');
            if ($handle) {
                fpassthru($handle);
                fclose($handle);
            }
        }, 200, [
            'Content-Type'  => $mime,
            'Content-Length'=> $size,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    // ── Private: 1×1 transparent PNG fallback ─────────────────────────────────

    private function unavailableResponse(string $message): \Illuminate\Http\Response
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        return response($pixel, 404, [
            'Content-Type'    => 'image/png',
            'X-Image-Status'  => 'unavailable',
            'X-Image-Message' => $message,
        ]);
    }
}
