<?php

/**
 * =========================================================================
 * MobilePhotoUploadTest — Mobile Foto-Aufnahme per QR-Code (Modul 4)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Signierte Upload-Seite zeigt alle Platzhalter-Slots
 *   - Ohne gültige Signatur: 403
 *   - Upload speichert das Foto mit Slot-Property; erneuter Upload auf
 *     denselben Slot ERSETZT das Foto (ein Foto je Slot)
 *
 * Muster: echte Tenant-Disk (kein Storage::fake über HTTP-Requests) +
 * tenancy()->end() und Storage-Cleanup im finally (siehe
 * WatchPhotoDownloadTest „serves stored photos …").
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\PhotoSlot;
use App\Models\Brand;
use App\Models\Watch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

it('serves the mobile upload page via signed link and stores slot photos', function () {
    $tenant = provisionTenant();

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watchId = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Foto GMT',
            ])->id;
        });

        $domain = $tenant->primaryDomain();

        $pageUrl = null;
        $storeUrl = null;

        $tenant->run(function () use (&$pageUrl, &$storeUrl, $watchId, $domain) {
            URL::forceRootUrl('http://'.$domain);

            $pageUrl = URL::temporarySignedRoute('watch.photos.mobile', now()->addDay(), ['watch' => $watchId]);
            $storeUrl = URL::temporarySignedRoute('watch.photos.mobile.store', now()->addDay(), ['watch' => $watchId]);
        });

        // Ohne Signatur: 403
        $this->get('http://'.$domain.'/uhren/'.$watchId.'/fotos')->assertForbidden();

        // Signierte Seite zeigt alle Slots + Tipps
        $page = $this->get($pageUrl)->assertOk();

        foreach (PhotoSlot::cases() as $slot) {
            $page->assertSee($slot->getLabel());
        }

        $page->assertSee('Tipps für gelungene Bilder');

        // Upload in den Front-Slot
        $this->post($storeUrl, [
            'slot' => PhotoSlot::Front->value,
            'photo' => UploadedFile::fake()->image('front.jpg', 800, 800),
        ])->assertOk()->assertJsonStructure(['url']);

        // Zweiter Upload in denselben Slot ERSETZT das Foto
        $this->post($storeUrl, [
            'slot' => PhotoSlot::Front->value,
            'photo' => UploadedFile::fake()->image('front-neu.jpg', 800, 800),
        ])->assertOk();

        $tenant->run(function () use ($watchId) {
            $watch = Watch::findOrFail($watchId);
            $frontPhotos = $watch->getMedia('photos')
                ->filter(fn ($item): bool => $item->getCustomProperty('slot') === PhotoSlot::Front->value);

            expect($frontPhotos)->toHaveCount(1)
                ->and($frontPhotos->first()->getCustomProperty('origin'))->toBe('mobile_upload');
        });
    } finally {
        tenancy()->end();
        File::deleteDirectory(storage_path('tenant'.$tenant->id));
        destroyTenant($tenant);
    }
});
