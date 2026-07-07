<?php

/**
 * =========================================================================
 * watches:migrate-photos — Alt-Fotos in die Media Library überführen
 * =========================================================================
 *
 * Zweck:
 *   Einmalige Datenmigration (Modul 3 → 4): Fotos aus der JSON-Spalte
 *   watches.photos (Pfade auf der public-Disk) werden als Media-Library-
 *   Einträge in die photos-Collection übernommen; danach wird die
 *   Alt-Spalte genullt.
 *
 * Nutzung (pro Tenant-Kontext, via stancl):
 *   php artisan tenants:run watches:migrate-photos
 *
 * Idempotent: Uhren ohne Alt-Fotos oder mit bereits vorhandenen
 * Media-Fotos werden übersprungen.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Watch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateWatchPhotosCommand extends Command
{
    protected $signature = 'watches:migrate-photos';

    protected $description = 'Überführt Alt-Fotos (watches.photos) in die Media Library (Collection photos)';

    public function handle(): int
    {
        $migrated = 0;

        Watch::withTrashed()
            ->whereNotNull('photos')
            ->each(function (Watch $watch) use (&$migrated): void {
                if ($watch->getMedia('photos')->isNotEmpty()) {
                    return;
                }

                /** @var array<int, mixed> $legacyPaths */
                $legacyPaths = (array) $watch->photos;

                foreach ($legacyPaths as $path) {
                    if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
                        continue;
                    }

                    // addMediaFromDisk VERSCHIEBT die Datei in die
                    // Media-Library-Struktur ({media-id}/{filename}).
                    $watch->addMediaFromDisk($path, 'public')
                        ->withCustomProperties(['origin' => 'legacy_photos_column'])
                        ->toMediaCollection('photos');

                    $migrated++;
                }

                $watch->forceFill(['photos' => null])->saveQuietly();
            });

        $this->info("Migriert: {$migrated} Foto(s).");

        return self::SUCCESS;
    }
}
