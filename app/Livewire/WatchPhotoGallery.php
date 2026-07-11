<?php

/**
 * =========================================================================
 * WatchPhotoGallery — Foto-Galerie mit Drag & Drop (Uhren-Formular)
 * =========================================================================
 *
 * Zweck:
 *   Zeigt ALLE Fotos einer Uhr (Slot- und freie Fotos) als sortierbares
 *   Raster: Reihenfolge per Ziehen ändern (Filament-x-sortable),
 *   „Als Hauptbild" schiebt ein Foto an Position 1. Die Reihenfolge
 *   (media.order_column) bestimmt Galerie und Shop-Kachel.
 *
 * Sicherheit: Aktionen nur mit update-Recht an der Uhr (WatchPolicy).
 * Eingebettet über Filament\Schemas\Components\Livewire (Fotos-Tab).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PhotoSlot;
use App\Models\Watch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class WatchPhotoGallery extends Component
{
    public Watch $watch;

    /**
     * Neue Reihenfolge speichern (IDs in Ziel-Reihenfolge) — nur Medien
     * DIESER Uhr werden berührt, fremde IDs werden ignoriert.
     *
     * @param  array<int, int|string>  $mediaIds
     */
    public function reorder(array $mediaIds): void
    {
        abort_unless(auth()->user()?->can('update', $this->watch) ?? false, 403);

        $media = $this->watch->getMedia('photos')->keyBy('id');
        $position = 1;

        foreach ($mediaIds as $id) {
            $item = $media->get((int) $id);

            if ($item !== null) {
                $item->order_column = $position++;
                $item->save();
            }
        }
    }

    /**
     * Foto an Position 1 schieben — das erste Bild ist das Hauptbild
     * in Shop-Kachel und Galerie.
     */
    public function makeMain(int $mediaId): void
    {
        abort_unless(auth()->user()?->can('update', $this->watch) ?? false, 403);

        $ids = $this->watch->getMedia('photos')->pluck('id')->all();

        if (! in_array($mediaId, $ids, true)) {
            return;
        }

        $this->reorder([$mediaId, ...array_values(array_diff($ids, [$mediaId]))]);
    }

    public function render(): View
    {
        $photos = $this->watch->getMedia('photos')->map(fn ($media): array => [
            'id' => (int) $media->getKey(),
            // Cache-Buster wie in Watch::photoUrls()
            'url' => $media->getUrl().'?v='.($media->updated_at?->getTimestamp() ?? 0),
            'slotLabel' => PhotoSlot::tryFrom((string) $media->getCustomProperty('slot'))?->getLabel(),
        ])->values();

        return view('livewire.watch-photo-gallery', ['photos' => $photos]);
    }
}
