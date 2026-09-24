<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class NetworkImagePathResolver
{
    private string $networkDrive;  // e.g. "Z:"
    private string $networkRoot;   // e.g. "\\FILE-SERVER\Share"  (may be empty)

    public function __construct()
    {
        $this->networkDrive = rtrim(
            strtoupper(config('network_images.network_drive', 'Z:')),
            '\\/'
        );
        $this->networkRoot = rtrim(
            config('network_images.network_root', ''),
            '\\/'
        );
    }

    /**
     * Resolve a stored path to a filesystem path that PHP on the server
     * can actually open.
     *
     * Supports:
     *   Z:\...          → translated using configured UNC root (if set)
     *   Z:/...          → same, normalised
     *   \\server\...    → returned as-is (already a UNC path)
     *   relative/path   → returned as-is (locally uploaded files handled elsewhere)
     *   null / empty    → returns null
     *
     * Returns null if the path cannot be safely resolved.
     */
    public function resolve(?string $storedPath): ?string
    {
        if (blank($storedPath)) {
            return null;
        }

        // Normalise forward slashes to backslashes
        $path = str_replace('/', '\\', $storedPath);

        // ── Case 1: already a UNC path (\\server\...) ─────────────────────────
        if (str_starts_with($path, '\\\\')) {
            return $this->validatePath($path, $storedPath);
        }

        // ── Case 2: mapped drive letter (Z:\...) ──────────────────────────────
        $drivePrefix = strtoupper($this->networkDrive) . '\\';

        if (str_starts_with(strtoupper($path), strtoupper($drivePrefix))) {
            // If no UNC root is configured, return the path as-is.
            // On dev machines where Z: is mapped, this works directly.
            if (empty($this->networkRoot)) {
                return $this->validatePath($path, $storedPath);
            }

            // Translate: remove the drive prefix, prepend the UNC root.
            $relative  = substr($path, strlen($drivePrefix));
            $resolved  = $this->networkRoot . '\\' . $relative;

            return $this->validatePath($resolved, $storedPath);
        }

        // ── Case 3: anything else (relative path, local path, etc.) ──────────
        // Return as-is — the caller will do its own file_exists check.
        return $path;
    }

    /**
     * Check whether the configured network root is accessible from this server.
     * Returns true if accessible, false otherwise.
     */
    public function isNetworkRootAccessible(): bool
    {
        if (empty($this->networkRoot)) {
            return false;
        }
        return is_dir($this->networkRoot) && is_readable($this->networkRoot);
    }

    public function getConfiguredDrive(): string  { return $this->networkDrive; }
    public function getConfiguredRoot(): string   { return $this->networkRoot; }
    public function isConfigured(): bool          { return ! empty($this->networkRoot); }

    /**
     * Validate that the resolved path does not escape the configured network
     * root via path traversal (../), and log the resolution for diagnostics.
     *
     * Returns the path if safe, null if a traversal attempt is detected.
     */
    private function validatePath(string $resolved, string $original): ?string
    {
        // Prevent path traversal: reject paths containing ..
        if (str_contains($resolved, '..')) {
            Log::warning('NetworkImagePathResolver: path traversal attempt blocked.', [
                'original' => $original,
                'resolved' => '[redacted]',
            ]);
            return null;
        }

        // If a UNC root is configured, the resolved path must start with it.
        if (! empty($this->networkRoot) && str_starts_with($resolved, '\\\\')) {
            $rootUpper     = strtoupper($this->networkRoot);
            $resolvedUpper = strtoupper($resolved);

            if (! str_starts_with($resolvedUpper, $rootUpper)) {
                Log::warning('NetworkImagePathResolver: resolved path escaped configured root.', [
                    'original' => $original,
                    'resolved' => '[redacted]',
                ]);
                return null;
            }
        }

        return $resolved;
    }
}
