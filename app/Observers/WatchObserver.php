<?php

/**
 * =========================================================================
 * WatchObserver — Automatischer Foto-Download nach dem Speichern
 * =========================================================================
 *
 * Zweck:
 *   Sobald eine Uhr mit KI-Rechercheergebnis (research_data.image_urls)
 *   gespeichert wird und noch keine Fotos hat, lädt die
 *   DownloadWatchPhotosAction die Bilder in den Tenant-Storage.
 *
 * WARUM synchron statt Queue-Job:
 *   Lokal läuft kein Queue-Worker (database-Driver, ADR-002); 2-4 kleine
 *   Downloads mit 20-s-Timeout sind vertretbar. Bei Produktions-Redis
 *   kann der Aufruf in einen ShouldQueue-Job wandern (TODO im Status).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Watches\DownloadWatchPhotosAction;
use App\Models\Watch;

class WatchObserver
{
    public function saved(Watch $watch): void
    {
        $imageSources = (array) data_get($watch->research_data, 'image_urls', []);

        // Nur wenn noch keine Fotos existieren — weder in der Media Library
        // noch in der Alt-Spalte photos (bis watches:migrate-photos lief).
        if ($imageSources !== [] && blank($watch->photos) && $watch->getMedia('photos')->isEmpty()) {
            app(DownloadWatchPhotosAction::class)->execute($watch);
        }
    }
}
