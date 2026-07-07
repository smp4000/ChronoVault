<?php

/**
 * =========================================================================
 * DownloadWatchPhotosAction — KI-Bildquellen als Uhrenfotos speichern
 * =========================================================================
 *
 * Zweck:
 *   Lädt die vom KI-Referenz-Lookup gesammelten Bild-URLs
 *   (watches.research_data → image_urls) herunter und speichert sie als
 *   Fotos der Uhr auf der public-Disk. Die Disk ist durch den
 *   FilesystemTenancyBootstrapper tenant-isoliert; ausgeliefert werden
 *   die Dateien über die stancl-Asset-Route (tenant_asset()).
 *
 * Verantwortlichkeiten:
 *   - Nur echte Bilder übernehmen (Content-Type image/*) — die KI liefert
 *     gelegentlich Produktseiten-URLs, die werden übersprungen
 *   - Fehlertoleranz pro URL (Timeout, 404, …) — ein kaputter Link darf
 *     den Rest nicht verhindern
 *   - Ergebnis via saveQuietly persistieren (KEINE Model-Events —
 *     der WatchObserver ruft diese Action aus saved() auf)
 *
 * Aufrufer:
 *   - App\Observers\WatchObserver (automatisch nach dem Speichern)
 *   - Modul 4 kann die Action für Re-Downloads wiederverwenden
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Watches;

use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
     * Lädt die KI-Bildquellen der Uhr herunter und speichert die Pfade
     * in watches.photos. Liefert die Anzahl gespeicherter Fotos.
     */
    public function execute(Watch $watch): int
    {
        /** @var array<int, mixed> $urls Rohdaten aus JSON — Typen ungesichert */
        $urls = array_slice((array) data_get($watch->research_data, 'image_urls', []), 0, self::MAX_PHOTOS);

        if ($urls === []) {
            return 0;
        }

        $paths = [];

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

                $path = "watches/{$watch->id}/ai-".($index + 1).".{$extension}";

                Storage::disk('public')->put($path, $response->body());
                $paths[] = $path;
            } catch (Throwable $e) {
                report($e);

                continue;
            }
        }

        if ($paths !== []) {
            // saveQuietly: der Observer ruft diese Action aus saved() auf —
            // normale Events würden eine Endlosschleife auslösen.
            $watch->forceFill(['photos' => $paths])->saveQuietly();
        }

        return count($paths);
    }
}
