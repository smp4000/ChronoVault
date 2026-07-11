{{--
=============================================================================
Livewire: Foto-Galerie mit Drag & Drop (Uhren-Formular, Fotos-Tab)
=============================================================================
Nutzt Filaments x-sortable-Alpine-Plugin (panelweit verfügbar). Inline-
Styles statt Tailwind-Klassen — das Panel-CSS enthält nur Filaments
kompilierte Auswahl. Erwartet: $photos (id/url/slotLabel).
=============================================================================
--}}
<div>
    @if ($photos->isEmpty())
        <p style="font-size: 0.875rem; opacity: 0.6;">
            Noch keine Fotos vorhanden — laden Sie oben Fotos hoch oder nutzen Sie den QR-Code.
        </p>
    @else
        <p style="font-size: 0.8125rem; opacity: 0.7; margin-bottom: 0.75rem;">
            Per Ziehen sortieren — das <strong>erste Bild ist das Hauptbild</strong> im Shop.
            Nach dem Verschieben wird die Reihenfolge automatisch gespeichert.
        </p>

        <div
            x-sortable
            x-on:end.stop="$wire.reorder($event.target.sortable.toArray())"
            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); gap: 0.75rem;"
        >
            @foreach ($photos as $index => $photo)
                <div
                    wire:key="gallery-{{ $photo['id'] }}"
                    x-sortable-item="{{ $photo['id'] }}"
                    x-sortable-handle
                    style="position: relative; border-radius: 0.75rem; overflow: hidden; border: 1px solid rgba(128,128,128,0.35); cursor: grab; background: rgba(128,128,128,0.08);"
                >
                    <img src="{{ $photo['url'] }}" alt="" loading="lazy"
                         style="display: block; width: 100%; aspect-ratio: 1 / 1; object-fit: cover; pointer-events: none;">

                    @if ($index === 0)
                        <span style="position: absolute; top: 0.4rem; left: 0.4rem; background: #1e40af; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 999px;">
                            Hauptbild
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="makeMain({{ $photo['id'] }})"
                            title="Als Hauptbild festlegen"
                            style="position: absolute; top: 0.4rem; left: 0.4rem; background: rgba(0,0,0,0.55); color: #fff; font-size: 0.65rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 999px; cursor: pointer; border: 0;"
                        >
                            ★ Hauptbild
                        </button>
                    @endif

                    @if ($photo['slotLabel'])
                        <span style="position: absolute; bottom: 0.4rem; left: 0.4rem; right: 0.4rem; background: rgba(0,0,0,0.55); color: #fff; font-size: 0.625rem; padding: 0.15rem 0.4rem; border-radius: 0.375rem; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $photo['slotLabel'] }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
