<?php

/**
 * =========================================================================
 * WatermarkPhotosTest — Wasserzeichen auf Uhrenfotos (Modul 4)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Wasserzeichen verändert die Bilddatei und markiert das Medium
 *     (custom_property watermarked)
 *   - Zweiter Lauf überspringt gestempelte Fotos (idempotent)
 *   - Datei bleibt ein gültiges Bild im Ursprungsformat
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Watches\WatermarkWatchPhotosAction;
use App\Models\Brand;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('stamps a watermark once per photo and keeps the file a valid image', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            // Echtes PNG via GD (groß genug für lesbaren Stempel)
            $canvas = imagecreatetruecolor(600, 400);
            $blue = imagecolorallocate($canvas, 30, 64, 175);
            imagefilledrectangle($canvas, 0, 0, 600, 400, $blue);
            ob_start();
            imagepng($canvas);
            $png = (string) ob_get_clean();
            imagedestroy($canvas);

            $watch->addMediaFromString($png)
                ->usingFileName('foto.png')
                ->toMediaCollection('photos');

            $media = $watch->getMedia('photos')->first();
            $bytesBefore = (string) file_get_contents($media->getPath());

            $action = app(WatermarkWatchPhotosAction::class);

            // Erster Lauf: stempelt 1 Foto
            expect($action->execute($watch->refresh(), 'LSW Chrono'))->toBe(1);

            $media = $watch->refresh()->getMedia('photos')->first();
            $bytesAfter = (string) file_get_contents($media->getPath());

            expect($media->getCustomProperty('watermarked'))->toBeTrue()
                ->and($bytesAfter)->not->toBe($bytesBefore)
                // Datei ist weiterhin ein gültiges Bild
                ->and(@imagecreatefromstring($bytesAfter))->not->toBeFalse();

            // Zweiter Lauf: nichts mehr zu stempeln
            expect($action->execute($watch->refresh(), 'LSW Chrono'))->toBe(0);
        });
    } finally {
        destroyTenant($tenant);
    }
});
