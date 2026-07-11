<?php

/**
 * =========================================================================
 * WatchPhotoUploadController — Mobile Foto-Aufnahme per QR-Code
 * =========================================================================
 *
 * Zweck:
 *   Der Händler scannt im Uhren-Formular einen QR-Code und landet auf
 *   einer schlanken Handy-Seite mit den Platzhalter-Slots des geführten
 *   Foto-Uploads (Vorderseite, Rückseite, …). Jedes Foto wird direkt
 *   der Uhr zugeordnet (Media-Collection photos, custom_property slot)
 *   — ein bestehendes Slot-Foto wird ersetzt.
 *
 * Sicherheit:
 *   Nur über den SIGNIERTEN Link erreichbar (24 h gültig) — kein Login
 *   auf dem Handy nötig, aber ohne gültige Signatur 403. Upload-POST
 *   zusätzlich gedrosselt.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PhotoSlot;
use App\Models\Watch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class WatchPhotoUploadController extends Controller
{
    /**
     * Mobile Upload-Seite: Platzhalter-Kacheln je Slot + Tipps-Dialog.
     */
    public function show(string $watchId): View
    {
        $watch = Watch::query()->with('brand')->findOrFail($watchId);

        $media = $watch->getMedia('photos');

        $slots = array_map(function (PhotoSlot $slot) use ($media): array {
            $existing = $media->first(
                fn ($item): bool => $item->getCustomProperty('slot') === $slot->value,
            );

            return [
                'value' => $slot->value,
                'label' => $slot->getLabel(),
                'photoUrl' => $existing?->getUrl(),
            ];
        }, PhotoSlot::cases());

        return view('mobile.photo-upload', [
            'watch' => $watch,
            'slots' => $slots,
            // Eigener signierter POST-Link (Signatur gilt je URL)
            'storeUrl' => URL::temporarySignedRoute(
                'watch.photos.mobile.store',
                now()->addDay(),
                ['watch' => $watch->getKey()],
            ),
        ]);
    }

    /**
     * Foto für einen Slot speichern — ersetzt das vorhandene Slot-Foto.
     */
    public function store(Request $request, string $watchId): JsonResponse
    {
        $watch = Watch::query()->findOrFail($watchId);

        $validated = $request->validate([
            'slot' => ['required', Rule::in(array_column(PhotoSlot::cases(), 'value'))],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
        ], [
            'photo.required' => 'Bitte wählen Sie ein Foto aus.',
            'photo.image' => 'Bitte laden Sie eine Bilddatei hoch.',
            'photo.mimes' => 'Erlaubte Formate: JPG, PNG, WebP.',
            'photo.max' => 'Das Foto ist zu groß (max. 15 MB).',
        ]);

        // Ein Foto je Slot: das alte Slot-Foto wird ersetzt
        $watch->getMedia('photos')
            ->filter(fn ($item): bool => $item->getCustomProperty('slot') === $validated['slot'])
            ->each(fn ($item) => $item->delete());

        $media = $watch->addMediaFromRequest('photo')
            ->withCustomProperties(['slot' => $validated['slot'], 'origin' => 'mobile_upload'])
            ->toMediaCollection('photos');

        return response()->json(['url' => $media->getUrl()]);
    }
}
