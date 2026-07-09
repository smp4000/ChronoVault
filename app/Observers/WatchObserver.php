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

use App\Actions\Transactions\RecordPurchaseAction;
use App\Actions\Watches\DownloadWatchPhotosAction;
use App\Models\Watch;

class WatchObserver
{
    /**
     * Uhren, die direkt mit Einkaufsdaten angelegt werden, bekommen
     * automatisch ihren Ankauf-Beleg — so ist die Preishistorie von
     * Anfang an vollständig (Modul 5).
     */
    public function created(Watch $watch): void
    {
        if ($watch->purchase_price !== null) {
            app(RecordPurchaseAction::class)->execute($watch, [
                'price' => (float) $watch->purchase_price,
                'transacted_at' => $watch->purchase_date ?? now(),
                'notes' => $watch->purchase_location !== null
                    ? "Automatisch aus der Uhren-Anlage übernommen (gekauft bei: {$watch->purchase_location})."
                    : 'Automatisch aus der Uhren-Anlage übernommen.',
            ], syncWatch: false);
        }
    }

    /**
     * Preissenkung erkennen: Sinkt der Angebotspreis, wird der alte
     * Preis als Streichpreis gemerkt (Shop zeigt Rabatt-Badge und
     * „Preis vor Preissenkung"). Bei Preiserhöhung oder Preis-Entfernung
     * wird der Streichpreis zurückgesetzt. Bei mehrfachen Senkungen
     * bleibt der URSPRÜNGLICHE (höchste) Preis stehen.
     */
    public function updating(Watch $watch): void
    {
        if (! $watch->isDirty('asking_price')) {
            return;
        }

        $old = $watch->getOriginal('asking_price');
        $new = $watch->getAttribute('asking_price');

        if ($old !== null && $new !== null && (float) $new < (float) $old) {
            // Erste Senkung merkt den Ausgangspreis; weitere behalten ihn
            if ($watch->getAttribute('previous_asking_price') === null) {
                $watch->setAttribute('previous_asking_price', $old);
            }

            $watch->setAttribute('price_reduced_at', now());

            return;
        }

        // Preis erhöht oder entfernt → kein Streichpreis mehr
        $watch->setAttribute('previous_asking_price', null);
        $watch->setAttribute('price_reduced_at', null);
    }

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
