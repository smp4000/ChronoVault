<?php

/**
 * =========================================================================
 * DownloadWatchPhotosAction — KI-Bildquellen als Uhrenfotos speichern
 * =========================================================================
 *
 * Zweck:
 *   Lädt die vom KI-Referenz-Lookup gesammelten Bild-URLs
 *   (watches.research_data → image_urls) herunter und legt sie als
 *   Media-Library-Einträge in der photos-Collection der Uhr ab
 *   (public-Disk, tenant-isoliert; URLs via TenantMediaUrlGenerator).
 *
 * Verantwortlichkeiten:
 *   - Nur echte Bilder übernehmen (Content-Type image/*) — die KI liefert
 *     gelegentlich Produktseiten-URLs, die werden übersprungen
 *   - Fehlertoleranz pro URL (Timeout, 404, …) — ein kaputter Link darf
 *     den Rest nicht verhindern
 *   - Herkunft dokumentieren (custom_properties: source_url, origin)
 *
 * Aufrufer:
 *   - App\Observers\WatchObserver (automatisch nach dem Speichern)
 *   - watches:migrate-photos nutzt die Collection ebenfalls (Alt-Daten)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Watches;

use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use Throwable;

class DownloadWatchPhotosAction
{
    /** Nicht mehr Bilder je Uhr automatisch übernehmen. */
    private const MAX_PHOTOS = 4;

    /** @var array<string, string> Content-Type → Dateiendung */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
    ];

    /**
     * Lädt die KI-Bildquellen der Uhr in die photos-Media-Collection.
     * Liefert die Anzahl gespeicherter Fotos.
     */
    public function execute(Watch $watch): int
    {
        /** @var array<int, mixed> $urls Rohdaten aus JSON — Typen ungesichert */
        $urls = array_slice((array) data_get($watch->research_data, 'image_urls', []), 0, self::MAX_PHOTOS);

        if ($urls === []) {
            return 0;
        }

        $stored = 0;

        foreach ($urls as $index => $url) {
            if (! is_string($url) || ! str_starts_with($url, 'http')) {
                continue;
            }

            try {
                $response = Http::timeout(20)->withHeaders([
                    // Manche CDNs blocken Requests ohne Browser-Kennung.
                    'User-Agent' => 'Mozilla/5.0 (compatible; ChronoVault/1.0)',
                ])->get($url);

                if ($response->failed()) {
                    continue;
                }

                $contentType = strtolower(strtok((string) $response->header('Content-Type'), ';') ?: '');
                $extension = self::EXTENSIONS[$contentType] ?? null;

                // Keine Bilddatei (z. B. Produktseiten-HTML) → überspringen.
                if ($extension === null || $response->body() === '') {
                    continue;
                }

                // Winzlinge aussortieren: Tracking-Pixel, Platzhalter und
                // Fehler-Antworten mit Bild-Content-Type sind wenige hundert
                // Bytes — ein echtes Produktfoto ist deutlich größer.
                if (strlen($response->body()) < 5120) {
                    continue;
                }

                $watch->addMediaFromString($response->body())
                    ->usingFileName('ai-'.($index + 1).'.'.$extension)
                    ->withCustomProperties(['origin' => 'ai_lookup', 'source_url' => $url])
                    ->toMediaCollection('photos');

                $stored++;
            } catch (Throwable $e) {
                report($e);

                continue;
            }
        }

        return $stored;
    }
}
