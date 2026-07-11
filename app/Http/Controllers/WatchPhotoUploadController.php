<?php

/**
 * =========================================================================
 * WatchPhotoUploadController — Mobile Foto-Aufnahme per QR-Code
 * =========================================================================
 *
 * Zweck:
 *   Der Händler scannt im Uhren-Formular einen QR-Code und landet auf
 *   einer schlanken Handy-Seite mit den Platzhalter-Slots des geführten
 *   Foto-Uploads (Vorderseite, Rückseite, …) plus „Weitere Fotos"
 *   (beliebig viele, ohne Slot). Die Fotos werden auf dem Handy erst
 *   GESAMMELT und mit „Übertragen" gemeinsam hochgeladen — erst dann
 *   wird ein bestehendes Slot-Foto ersetzt; freie Fotos werden ergänzt.
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
     * Foto speichern: Slot-Fotos ersetzen das vorhandene Slot-Foto,
     * freie Fotos (slot = "extra") werden ergänzt.
     */
    public function store(Request $request, string $watchId): JsonResponse
    {
        $watch = Watch::query()->findOrFail($watchId);

        $slotValues = [...array_column(PhotoSlot::cases(), 'value'), 'extra'];

        $validated = $request->validate([
            'slot' => ['required', Rule::in($slotValues)],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
        ], [
            'photo.required' => 'Bitte wählen Sie ein Foto aus.',
            'photo.image' => 'Bitte laden Sie eine Bilddatei hoch.',
            'photo.mimes' => 'Erlaubte Formate: JPG, PNG, WebP.',
            'photo.max' => 'Das Foto ist zu groß (max. 15 MB).',
        ]);

        $isExtra = $validated['slot'] === 'extra';

        if (! $isExtra) {
            // Ein Foto je Slot: das alte Slot-Foto wird ersetzt
            $watch->getMedia('photos')
                ->filter(fn ($item): bool => $item->getCustomProperty('slot') === $validated['slot'])
                ->each(fn ($item) => $item->delete());
        }

        $media = $watch->addMediaFromRequest('photo')
            ->withCustomProperties(array_filter([
                'slot' => $isExtra ? null : $validated['slot'],
                'origin' => 'mobile_upload',
            ]))
            ->toMediaCollection('photos');

        return response()->json(['url' => $media->getUrl()]);
    }
}
