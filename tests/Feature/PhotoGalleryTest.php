<?php

/**
 * =========================================================================
 * PhotoGalleryTest — Foto-Galerie mit Drag & Drop (Uhren-Formular)
 * =========================================================================
 *
 * Abgedeckt:
 *   - reorder() speichert die neue Reihenfolge (order_column) und
 *     ignoriert fremde Media-IDs
 *   - makeMain() schiebt ein Foto an Position 1 (= Hauptbild)
 *   - photoUrls()/firstPhotoUrl() folgen der Sortier-Reihenfolge
 *   - Ohne update-Recht: 403
 * =========================================================================
 */

declare(strict_types=1);

use App\Livewire\WatchPhotoGallery;
use App\Models\Brand;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('reorders photos and sets the main image via the gallery component', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Storage::fake('public');
            Http::fake();

            $this->actingAs(User::where('email', 'owner@example.test')->firstOrFail());

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            foreach (['eins.gif', 'zwei.gif', 'drei.gif'] as $name) {
                $watch->addMediaFromString(tinyGif())
                    ->usingFileName($name)
                    ->toMediaCollection('photos');
            }

            [$first, $second, $third] = $watch->getMedia('photos')->pluck('id')->all();

            // Reihenfolge umdrehen (drei, zwei, eins) — fremde ID wird ignoriert
            Livewire::test(WatchPhotoGallery::class, ['watch' => $watch])
                ->call('reorder', [$third, 999999, $second, $first]);

            expect($watch->refresh()->getMedia('photos')->pluck('file_name')->all())
                ->toBe(['drei.gif', 'zwei.gif', 'eins.gif'])
                ->and($watch->firstPhotoUrl())->toContain('drei.gif');

            // "Als Hauptbild": zwei.gif nach vorne
            Livewire::test(WatchPhotoGallery::class, ['watch' => $watch])
                ->call('makeMain', $second);

            expect($watch->refresh()->getMedia('photos')->pluck('file_name')->all())
                ->toBe(['zwei.gif', 'drei.gif', 'eins.gif'])
                ->and($watch->firstPhotoUrl())->toContain('zwei.gif');
        });
    } finally {
        destroyTenant($tenant);
    }
});
