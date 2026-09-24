<?php

namespace App\Console\Commands;

use App\Services\NetworkImagePathResolver;
use Illuminate\Console\Command;

class DiagnoseNetworkImages extends Command
{
    protected $signature   = 'images:diagnose {--record= : Test a specific id_number from the database}';
    protected $description = 'Diagnose network image path configuration and accessibility on this server.';

    public function handle(NetworkImagePathResolver $resolver): int
    {
        $this->info('');
        $this->info('=== Network Image Diagnostics ===');
        $this->info('');

        // ── Configuration ──────────────────────────────────────────────────────
        $this->line('<fg=cyan>Configuration:</>');
        $this->line('  Configured drive : <fg=yellow>' . ($resolver->getConfiguredDrive() ?: '(not set)') . '</>');
        $this->line('  Configured UNC   : <fg=yellow>' . ($resolver->getConfiguredRoot()  ?: '(not set — Z: drive will be used as-is)') . '</>');
        $this->line('  UNC configured   : ' . ($resolver->isConfigured() ? '<fg=green>YES</>' : '<fg=red>NO</>'));
        $this->info('');

        // ── Network root accessibility ─────────────────────────────────────────
        if ($resolver->isConfigured()) {
            $accessible = $resolver->isNetworkRootAccessible();
            $this->line('Network root accessible from this server : '
                . ($accessible ? '<fg=green>YES</>' : '<fg=red>NO — Apache/PHP cannot read the share</>'));

            if (! $accessible) {
                $this->warn('');
                $this->warn('  The PHP process cannot access the configured UNC root.');
                $this->warn('  This is a Windows permission issue, not an application bug.');
                $this->warn('  The Windows account running Apache on DC1 needs read');
                $this->warn('  permission to: ' . $resolver->getConfiguredRoot());
            }
        } else {
            $this->warn('  ID_TRACKER_NETWORK_ROOT is not set in .env.');
            $this->warn('  Z: paths will be passed to PHP as-is.');
            $this->warn('  This only works if Z: is mapped for the Apache service account.');
        }

        $this->info('');

        // ── Test a specific record ─────────────────────────────────────────────
        $idNumber = $this->option('record');

        if ($idNumber) {
            $record = \App\Models\IdRecord::where('id_number', $idNumber)->first();

            if (! $record) {
                $this->error("No record found with id_number = {$idNumber}");
                return self::FAILURE;
            }

            $this->line('<fg=cyan>Record test — IDNO: ' . $idNumber . '</>');
            $this->info('');

            foreach (['image' => [$record->image_path, $record->image_source],
                      'signature' => [$record->signature_path, $record->signature_source]] as $type => [$path, $source]) {

                $this->line("  [{$type}]");
                $this->line("    source       : " . ($source ?? 'null'));
                $this->line("    stored path  : " . ($path   ?? '(empty)'));

                if ($source === 'network' && $path) {
                    $resolved = $resolver->resolve($path);
                    $this->line("    resolved     : " . ($resolved ?? '<fg=red>(null — blocked)</>'));

                    if ($resolved) {
                        $exists   = file_exists($resolved);
                        $readable = $exists && is_readable($resolved);
                        $this->line("    file exists  : " . ($exists   ? '<fg=green>YES</>' : '<fg=red>NO</>'));
                        $this->line("    file readable: " . ($readable ? '<fg=green>YES</>' : '<fg=red>NO</>'));

                        if (! $exists) {
                            $this->warn("    ← File not found at resolved path.");
                            $this->warn("      Check that the UNC path is correct and the share is mounted.");
                        } elseif (! $readable) {
                            $this->warn("    ← File exists but is not readable.");
                            $this->warn("      Check Windows ACL permissions for the Apache service account.");
                        } else {
                            $this->line("    <fg=green>✓ File is accessible. Image should display.</>"); 
                        }
                    }
                } elseif ($source === 'upload') {
                    $uploadPath = $type === 'image' ? $record->image_upload_path : $record->signature_upload_path;
                    $exists = \Illuminate\Support\Facades\Storage::disk('public')->exists($uploadPath ?? '');
                    $this->line("    upload path  : " . ($uploadPath ?? '(empty)'));
                    $this->line("    in storage   : " . ($exists ? '<fg=green>YES</>' : '<fg=red>NO</>'));
                } else {
                    $this->line("    <fg=yellow>(no path stored)</>");
                }

                $this->info('');
            }
        } else {
            $this->line('Tip: test a specific record with --record=IDNO');
            $this->line('Example: php artisan images:diagnose --record=200596');
        }

        return self::SUCCESS;
    }
}
