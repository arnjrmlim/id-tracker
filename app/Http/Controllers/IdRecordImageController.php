<?php

namespace App\Http\Controllers;

use App\Models\IdRecord;
use App\Services\NetworkImagePathResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

    public function __construct(
        private readonly NetworkImagePathResolver $resolver
    ) {}

    // ── Public routes ──────────────────────────────────────────────────────────

    public function image(IdRecord $idRecord): StreamedResponse|\Illuminate\Http\Response
    {
        Gate::authorize('view', $idRecord);

        if ($idRecord->image_source === IdRecord::SOURCE_UPLOAD) {
            return $this->serveStoredFile($idRecord->image_upload_path, 'ID image not available.');
        }

        return $this->streamNetworkFile(
            $idRecord->image_path,
            'ID image not available.',
            $idRecord->id,
            'image'
        );
    }

    public function signature(IdRecord $idRecord): StreamedResponse|\Illuminate\Http\Response
    {
        Gate::authorize('view', $idRecord);

        if ($idRecord->signature_source === IdRecord::SOURCE_UPLOAD) {
            return $this->serveStoredFile($idRecord->signature_upload_path, 'Signature image not available.');
        }

        return $this->streamNetworkFile(
            $idRecord->signature_path,
            'Signature image not available.',
            $idRecord->id,
            'signature'
        );
    }

    // ── Private: serve uploaded file from Laravel public storage ──────────────

    private function serveStoredFile(
        ?string $storagePath,
        string  $notFoundMessage
    ): StreamedResponse|\Illuminate\Http\Response {
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
            'Content-Type'   => $mime,
            'Content-Length' => $size,
            'Cache-Control'  => 'private, max-age=300',
        ]);
    }

    // ── Private: stream a network/local filesystem path ───────────────────────

    /**
     * Resolves the stored path (which may be a Z:\ mapped drive path or a
     * UNC path) through NetworkImagePathResolver before attempting to read
     * the file.  This allows DC1's Apache/PHP process to access the file
     * via a configured UNC root even when Z:\ is not mapped on the server.
     */
    private function streamNetworkFile(
        ?string $storedPath,
        string  $notFoundMessage,
        int     $recordId,
        string  $imageType
    ): StreamedResponse|\Illuminate\Http\Response {
        if (blank($storedPath)) {
            return $this->unavailableResponse($notFoundMessage);
        }

        // Resolve Z:\... → \\UNC-ROOT\... (or return as-is if already UNC
        // or if no UNC root is configured).
        $resolvedPath = $this->resolver->resolve($storedPath);

        if ($resolvedPath === null) {
            Log::warning('Image path could not be resolved.', [
                'record_id'  => $recordId,
                'image_type' => $imageType,
                'reason'     => 'resolver returned null (possible traversal attempt)',
            ]);
            return $this->unavailableResponse($notFoundMessage);
        }

        if (! file_exists($resolvedPath) || ! is_readable($resolvedPath)) {
            Log::info('Image file not accessible from server.', [
                'record_id'     => $recordId,
                'image_type'    => $imageType,
                'stored_path'   => $storedPath,
                'resolved_path' => $resolvedPath,
                'unc_configured'=> $this->resolver->isConfigured(),
                'hint'          => $this->resolver->isConfigured()
                    ? 'Check that the Apache/PHP process on DC1 has read access to the network share.'
                    : 'No UNC root configured. Set ID_TRACKER_NETWORK_ROOT in .env.',
            ]);
            return $this->unavailableResponse($notFoundMessage);
        }

        $size = filesize($resolvedPath);
        if ($size === false || $size > 20 * 1024 * 1024) {
            return $this->unavailableResponse($notFoundMessage);
        }

        $mime = mime_content_type($resolvedPath);
        if ($mime === false || ! in_array($mime, self::ALLOWED_MIME, true)) {
            Log::warning('Image file has disallowed MIME type.', [
                'record_id'  => $recordId,
                'image_type' => $imageType,
                'mime'       => $mime,
            ]);
            return $this->unavailableResponse($notFoundMessage);
        }

        return response()->stream(function () use ($resolvedPath) {
            $handle = fopen($resolvedPath, 'rb');
            if ($handle) {
                fpassthru($handle);
                fclose($handle);
            }
        }, 200, [
            'Content-Type'   => $mime,
            'Content-Length' => $size,
            'Cache-Control'  => 'private, max-age=300',
        ]);
    }

    // ── Private: 1×1 transparent PNG fallback ─────────────────────────────────

    private function unavailableResponse(string $message): \Illuminate\Http\Response
    {
        $pixel = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        );

        return response($pixel, 404, [
            'Content-Type'    => 'image/png',
            'X-Image-Status'  => 'unavailable',
            'X-Image-Message' => $message,
        ]);
    }
}
