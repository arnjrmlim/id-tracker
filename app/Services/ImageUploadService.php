<?php

namespace App\Services;

use App\Models\IdRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /** Storage disk for uploaded images. */
    private const DISK = 'public';

    /** Max file size in KB (5 MB). */
    public const MAX_KB = 5120;

    /** Allowed MIME types. */
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Allowed extensions for Laravel validation. */
    public const ALLOWED_EXTENSIONS = 'jpg,jpeg,png,webp';

    /**
     * Store an uploaded ID image and return its relative storage path.
     * Filename: {id_number}_{random6}.{ext}
     */
    public function storeIdImage(UploadedFile $file, string $idNumber): string
    {
        return $this->store($file, $idNumber, 'id-images', '');
    }

    /**
     * Store an uploaded signature image and return its relative storage path.
     * Filename: {id_number}_signature_{random6}.{ext}
     */
    public function storeSignature(UploadedFile $file, string $idNumber): string
    {
        return $this->store($file, $idNumber, 'signatures', '_signature');
    }

    /**
     * Delete a previously uploaded file from storage.
     * NEVER call this for network/filesystem paths — only for Laravel-managed uploads.
     */
    public function deleteUploadedFile(?string $storagePath): void
    {
        if (blank($storagePath)) {
            return;
        }

        if (Storage::disk(self::DISK)->exists($storagePath)) {
            Storage::disk(self::DISK)->delete($storagePath);
            Log::info("Deleted uploaded image: {$storagePath}");
        }
    }

    /**
     * Internal: store file and return the relative path.
     */
    private function store(UploadedFile $file, string $idNumber, string $folder, string $suffix): string
    {
        $ext      = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $random   = Str::random(6);
        $safeId   = preg_replace('/[^a-zA-Z0-9_-]/', '_', $idNumber);
        $filename = "{$safeId}{$suffix}_{$random}.{$ext}";
        $subDir   = now()->format('Y/m');
        $path     = "{$folder}/{$subDir}";

        $file->storeAs($path, $filename, self::DISK);

        return "{$path}/{$filename}";
    }
}
