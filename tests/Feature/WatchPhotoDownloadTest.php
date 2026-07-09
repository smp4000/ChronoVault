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

use App\Enums\PhotoSlot;
use App\Models\Brand;
use App\Models\Watch;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// Kleinstes gültiges GIF (1×1) — die Media-Collection prüft den ECHTEN
// Datei-MIME, Fake-Text-Bytes würden abgelehnt.
function tinyGif(): string
{
    return base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

// "Download-taugliches" GIF: der Foto-Download verwirft Winzlinge
// (< 5 KB — Tracking-Pixel/Platzhalter), daher aufgepolstert. Die
// Magic Bytes am Anfang bleiben gültig → finfo erkennt image/gif.
function downloadableGif(): string
{
    return tinyGif().str_repeat("\0", 6000);
}

it('downloads ai image sources as photos after saving', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake([
                'cdn.example.com/*' => Http::response(downloadableGif(), 200, ['Content-Type' => 'image/gif']),
                'shop.example.com/*' => Http::response('<html>Produktseite</html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
                'kaputt.example.com/*' => Http::response('', 404),
                // Tracking-Pixel/Platzhalter (winzig, aber image/*) → übersprungen
                'pixel.example.com/*' => Http::response(tinyGif(), 200, ['Content-Type' => 'image/gif']),
            ]);

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'research_data' => [
                    'image_urls' => [
                        'https://cdn.example.com/a.jpg',
                        'https://shop.example.com/produkt',   // HTML → übersprungen
                        'https://pixel.example.com/px.gif',   // Winzling → übersprungen
                        'https://cdn.example.com/c.jpg',
                    ],
                ],
            ]);

            $watch->refresh();
            $media = $watch->getMedia('photos');

            expect($media)->toHaveCount(2)
                ->and($media[0]->file_name)->toBe('ai-1.gif')
                ->and($media[1]->file_name)->toBe('ai-4.gif')
                ->and($media[0]->getCustomProperty('origin'))->toBe('ai_lookup')
                ->and($media[0]->getCustomProperty('source_url'))->toBe('https://cdn.example.com/a.jpg')
                ->and(Storage::disk('public')->exists($media[0]->getPathRelativeToRoot()))->toBeTrue()
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

it('stores slotted photos and keeps slots separate from free photos', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            // Slot-Foto (geführter Upload) + freies Foto in derselben Collection
            $watch->addMediaFromString(tinyGif())
                ->usingFileName('front.gif')
                ->withCustomProperties(['slot' => PhotoSlot::Front->value])
                ->toMediaCollection('photos');

            $watch->addMediaFromString(tinyGif())
                ->usingFileName('frei.gif')
                ->toMediaCollection('photos');

            $media = $watch->getMedia('photos');
            $slotted = $media->filter(fn ($m): bool => $m->getCustomProperty('slot') === 'front');
            $free = $media->filter(fn ($m): bool => blank($m->getCustomProperty('slot')));

            expect($media)->toHaveCount(2)
                ->and($slotted)->toHaveCount(1)
                ->and($free)->toHaveCount(1)
                ->and($slotted->first()->file_name)->toBe('front.gif');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('replaces the brand logo because the collection is single file', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');

            $brand = Brand::where('name', 'Rolex')->firstOrFail();

            $brand->addMediaFromString(tinyGif())->usingFileName('logo-alt.gif')->toMediaCollection('logo');
            $brand->addMediaFromString(tinyGif())->usingFileName('logo-neu.gif')->toMediaCollection('logo');

            $logos = $brand->getMedia('logo');

            expect($logos)->toHaveCount(1)
                ->and($logos->first()->file_name)->toBe('logo-neu.gif');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('does not download again when photos already exist', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake([
                'cdn.example.com/*' => Http::response(downloadableGif(), 200, ['Content-Type' => 'image/gif']),
            ]);

            // Erste Speicherung lädt herunter …
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'research_data' => ['image_urls' => ['https://cdn.example.com/a.jpg']],
            ]);

            expect($watch->getMedia('photos'))->toHaveCount(1);

            // … erneutes Speichern löst KEINEN weiteren Download aus.
            $watch->update(['notes' => 'geändert']);

            expect($watch->refresh()->getMedia('photos'))->toHaveCount(1);
            Http::assertSentCount(1);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('converts webp photos to jpeg for e-mail embedding', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            // Echtes WebP via GD erzeugen (Outlook kann WebP nicht anzeigen)
            $canvas = imagecreatetruecolor(4, 4);
            ob_start();
            imagewebp($canvas);
            $webp = (string) ob_get_clean();
            imagedestroy($canvas);

            $watch->addMediaFromString($webp)
                ->usingFileName('cdn-foto.webp')
                ->toMediaCollection('photos');

            $photo = $watch->refresh()->firstPhotoForEmail();

            expect($photo)->not->toBeNull()
                ->and($photo['mime'])->toBe('image/jpeg')
                ->and($photo['name'])->toBe('cdn-foto.jpg')
                ->and(str_starts_with($photo['data'], "\xFF\xD8"))->toBeTrue();

            // JPEGs bleiben unangetastet
            $watch->clearMediaCollection('photos');
            $watch->addMediaFromString(tinyGif())
                ->usingFileName('normal.gif')
                ->toMediaCollection('photos');

            $gifPhoto = $watch->refresh()->firstPhotoForEmail();

            expect($gifPhoto['mime'])->toBe('image/gif')
                ->and($gifPhoto['name'])->toBe('normal.gif');
        });
    } finally {
        destroyTenant($tenant);
    }
});
