<?php

/**
 * =========================================================================
 * WatchPhotoDownloadTest — Automatischer Foto-Download (Modul 3)
 * =========================================================================
 *
 * Abgedeckt (Http- und Storage-Fakes, kein echtes Netzwerk):
 *   - Observer lädt KI-Bildquellen nach dem Speichern herunter
 *   - Nicht-Bilder (HTML-Produktseiten) und Fehler-URLs werden übersprungen
 *   - Kein erneuter Download, wenn bereits Fotos vorliegen
 * =========================================================================
 */

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Watch;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('downloads ai image sources as photos after saving', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake([
                'cdn.example.com/*' => Http::response('fake-jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
                'shop.example.com/*' => Http::response('<html>Produktseite</html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
                'kaputt.example.com/*' => Http::response('', 404),
            ]);

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'research_data' => [
                    'image_urls' => [
                        'https://cdn.example.com/a.jpg',
                        'https://shop.example.com/produkt',   // HTML → übersprungen
                        'https://kaputt.example.com/b.jpg',   // 404 → übersprungen
                        'https://cdn.example.com/c.jpg',
                    ],
                ],
            ]);

            $watch->refresh();

            expect($watch->photos)->toHaveCount(2)
                ->and($watch->photos[0])->toBe("watches/{$watch->id}/ai-1.jpg")
                ->and($watch->photos[1])->toBe("watches/{$watch->id}/ai-4.jpg")
                ->and(Storage::disk('public')->exists($watch->photos[0]))->toBeTrue()
                ->and($watch->firstPhotoUrl())->toContain('/tenancy/assets/');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('serves stored photos through the tenant asset route', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            // Echte (tenant-gesuffixte) public-Disk — kein Storage::fake,
            // die Asset-Route liest direkt aus storage_path('app/public').
            Storage::disk('public')->put('watches/test/foto.png', 'png-bytes');
        });

        $response = $this->get('http://'.$tenant->primaryDomain().'/tenancy/assets/watches/test/foto.png');

        $response->assertOk();

        // BinaryFileResponse: Inhalt über die ausgelieferte Datei prüfen
        expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe('png-bytes');
    } finally {
        // Der Request initialisiert Tenancy und beendet sie NICHT (kein
        // Terminate-Revert) — ohne end() räumt PHPUnit auf der gelöschten
        // Tenant-Verbindung auf und maskiert das Testergebnis.
        tenancy()->end();

        // Tenant-Storage-Verzeichnis wieder aufräumen (destroyTenant löscht nur die DB)
        File::deleteDirectory(storage_path('tenant'.$tenant->id));
        destroyTenant($tenant);
    }
});

it('does not download again when photos already exist', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'photos' => ['watches/x/ai-1.jpg'],
                'research_data' => ['image_urls' => ['https://cdn.example.com/a.jpg']],
            ]);

            // Erneutes Speichern darf keinen Download auslösen
            $watch->update(['notes' => 'geändert']);

            Http::assertNothingSent();
            expect($watch->refresh()->photos)->toBe(['watches/x/ai-1.jpg']);
        });
    } finally {
        destroyTenant($tenant);
    }
});
