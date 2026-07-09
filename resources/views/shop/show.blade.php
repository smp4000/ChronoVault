{{--
=============================================================================
Shop-Detailseite — Foto-Galerie, Preis, Spezifikationen, Beschreibung
=============================================================================
Erwartet: $watch (mit brand/caliber/media), $related (Collection).
Galerie: Hauptbild + Thumbnails; der Wechsel läuft über wenige Zeilen
Vanilla-JS (nur src-Tausch) — bewusst ohne Framework-Abhängigkeit.
=============================================================================
--}}
@extends('shop.layout')

@section('title', $watch->fullName())
@section('meta_description', str(strip_tags($watch->description ?? ''))->limit(150)->toString() ?: $watch->fullName().' bei '.tenant('name'))

@php
    use App\Enums\WatchFunction;

    $photos = $watch->photoUrls();

    $functionLabels = collect($watch->functions ?? [])
        ->map(fn ($code) => WatchFunction::tryFrom($code)?->getLabel())
        ->filter()
        ->values();

    // Lieferumfang aus Box/Papiere-Flags + Freitext zusammensetzen
    $deliveryParts = array_filter([
        $watch->has_box ? 'Originalbox' : null,
        $watch->has_papers ? 'Papiere' : null,
        $watch->delivery_scope,
    ]);

    $formatMm = fn ($value) => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',').' mm';

    // Spezifikationen gruppiert; null-Werte werden beim Rendern übersprungen
    $specGroups = [
        'Basisdaten' => [
            'Marke' => $watch->brand->name,
            'Modell' => $watch->model_name,
            'Referenznummer' => $watch->reference_number,
            'Baujahr' => $watch->production_year
                ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year
                : null,
            'Zustand' => $watch->condition?->getLabel(),
            'Geschlecht' => $watch->gender?->getLabel(),
            'Aufzug' => $watch->movement_type?->getLabel(),
            'Kaliber' => $watch->caliber?->name,
            'Gangreserve' => $watch->caliber?->power_reserve_hours ? $watch->caliber->power_reserve_hours.' Std.' : null,
            'Frequenz der Unruh' => $watch->caliber?->frequency_vph ? number_format((int) $watch->caliber->frequency_vph, 0, ',', '.').' A/h' : null,
            'Funktionen' => $functionLabels->isNotEmpty() ? $functionLabels->implode(', ') : null,
            'Limitierte Auflage' => $watch->is_limited_edition
                ? trim(($watch->limited_edition_number ? 'Nr. '.$watch->limited_edition_number : '').($watch->limited_edition_total ? ' von '.$watch->limited_edition_total : '')) ?: 'Ja'
                : null,
        ],
        'Gehäuse' => [
            'Material' => $watch->case_material?->getLabel(),
            'Durchmesser' => $watch->case_diameter_mm ? $formatMm($watch->case_diameter_mm) : null,
            'Höhe' => $watch->case_height_mm ? $formatMm($watch->case_height_mm) : null,
            'Glas' => $watch->glass_type?->getLabel(),
            'Lünette' => $watch->bezel_material?->getLabel(),
            'Lünettenfarbe' => $watch->bezel_color?->getLabel(),
            'Lünettentyp' => $watch->bezel_type?->getLabel(),
            'Gehäuseboden' => $watch->case_back?->getLabel(),
            'Wasserdichtigkeit' => $watch->water_resistance_bar ? $watch->water_resistance_bar.' bar' : null,
        ],
        'Zifferblatt & Band' => [
            'Zifferblatt' => $watch->dial_color?->getLabel(),
            'Ziffern' => $watch->dial_numerals?->getLabel(),
            'Finish' => $watch->dial_finish,
            'Bandmaterial' => $watch->bracelet_material?->getLabel(),
            'Bandfarbe' => $watch->bracelet_color?->getLabel(),
            'Schließe' => $watch->clasp_type?->getLabel(),
            'Schließenmaterial' => $watch->clasp_material?->getLabel(),
            'Bandanstoßbreite' => $watch->lug_width_mm ? $watch->lug_width_mm.' mm' : null,
            'Bandanstoß zu Bandanstoß' => $watch->lug_to_lug_mm ? rtrim(rtrim(number_format((float) $watch->lug_to_lug_mm, 2, ',', '.'), '0'), ',').' mm' : null,
        ],
        'Lieferumfang' => [
            'Umfang' => $deliveryParts !== [] ? implode(', ', $deliveryParts) : null,
        ],
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8 lg:pt-12">

        {{-- Breadcrumb zurück zur Kollektion --}}
        <a href="{{ route('shop.index') }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 transition hover:text-blue-800">
            &larr; Zur Kollektion
        </a>

        <div class="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">

            {{-- Foto-Galerie --}}
            <div>
                <div class="aspect-square overflow-hidden rounded-3xl border border-neutral-200 bg-neutral-50">
                    @if ($photos !== [])
                        <img id="shop-main-photo"
                             src="{{ $photos[0] }}"
                             alt="{{ $watch->fullName() }}"
                             class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <svg class="h-16 w-16 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    @endif
                </div>

                @if (count($photos) > 1)
                    <div class="mt-4 grid grid-cols-5 gap-3">
                        @foreach ($photos as $photo)
                            <button type="button"
                                    data-photo="{{ $photo }}"
                                    class="shop-thumb aspect-square overflow-hidden rounded-xl border transition {{ $loop->first ? 'border-blue-800 ring-1 ring-blue-800' : 'border-neutral-200 hover:border-blue-300' }}">
                                <img src="{{ $photo }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Kopfdaten, Preis, Anfrage --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">
                    {{ $watch->brand->name }}
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                    {{ $watch->model_name }}
                </h1>
                @if ($watch->reference_number)
                    <p class="mt-2 text-sm text-neutral-500">Referenz {{ $watch->reference_number }}</p>
                @endif

                <div class="mt-6">
                    @if (! $watch->isBuyableInShop())
                        {{-- Verkauft/Reserviert/In Auktion: Badge statt Kauf-Button --}}
                        <span class="inline-flex items-center gap-2 rounded-full bg-neutral-900 px-5 py-2 text-sm font-semibold tracking-wide text-white">
                            <span class="h-2 w-2 rounded-full {{ $watch->shopStatusBadge() === 'Verkauft' ? 'bg-neutral-400' : ($watch->shopStatusBadge() === 'Reserviert' ? 'bg-amber-400' : 'bg-blue-400') }}"></span>
                            {{ $watch->shopStatusBadge() }}
                        </span>
                        @if ($watch->formattedAskingPrice())
                            <p class="mt-4 text-2xl font-semibold text-neutral-400 line-through decoration-1">{{ $watch->formattedAskingPrice() }}</p>
                        @endif
                        <p class="mt-2 text-sm text-neutral-500">
                            {{ $watch->shopStatusBadge() === 'In Auktion'
                                ? 'Diese Uhr wird aktuell in unserer Auktion angeboten.'
                                : 'Diese Uhr ist nicht mehr verfügbar — gerne informieren wir Sie über vergleichbare Stücke.' }}
                        </p>
                    @elseif ($watch->formattedAskingPrice())
                        <p class="text-3xl font-semibold text-blue-900">{{ $watch->formattedAskingPrice() }}</p>
                        <p class="mt-1 text-xs text-neutral-400">inkl. MwSt., zzgl. Versand</p>

                        <a href="{{ route('shop.buy', $watch) }}"
                           class="mt-4 inline-flex items-center justify-center rounded-full bg-blue-800 px-8 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                            Jetzt verbindlich kaufen
                        </a>
                    @else
                        <p class="text-2xl font-medium text-neutral-700">Preis auf Anfrage</p>
                    @endif

                    @error('purchase')
                        <p class="mt-3 rounded-xl bg-red-50 px-4 py-2.5 text-sm text-red-900">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Schnell-Merkmale als Chips --}}
                <div class="mt-6 flex flex-wrap gap-2">
                    @if ($watch->condition)
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-900">{{ $watch->condition->getLabel() }}</span>
                    @endif
                    @if ($watch->has_box)
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700">Mit Box</span>
                    @endif
                    @if ($watch->has_papers)
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700">Mit Papieren</span>
                    @endif
                    @if ($watch->production_year)
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700">
                            {{ ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year }}
                        </span>
                    @endif
                </div>

                {{-- Anfrage-Formular --}}
                @if (session('inquiry_success'))
                    <div class="mt-8 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-900">
                        {{ session('inquiry_success') }}
                    </div>
                @else
                    <div class="mt-8 rounded-2xl border border-blue-100 bg-blue-50/50 p-6">
                        @if ($watch->isBuyableInShop())
                            <p class="font-medium text-neutral-900">Interesse an dieser Uhr?</p>
                            <p class="mt-1 text-sm leading-relaxed text-neutral-600">
                                Senden Sie uns eine Anfrage — wir beraten Sie gerne persönlich,
                                auch zu Inzahlungnahme und Versand.
                            </p>
                        @else
                            <p class="font-medium text-neutral-900">Interesse an einer vergleichbaren Uhr?</p>
                            <p class="mt-1 text-sm leading-relaxed text-neutral-600">
                                Senden Sie uns eine Anfrage — wir halten gerne Ausschau nach
                                einem vergleichbaren Stück für Sie.
                            </p>
                        @endif

                        <form method="POST" action="{{ route('shop.inquire', $watch) }}" class="mt-4">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="inquiry_name" class="block text-xs font-medium text-neutral-600">Name *</label>
                                    <input type="text" id="inquiry_name" name="name" required
                                           value="{{ old('name') }}"
                                           class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="inquiry_email" class="block text-xs font-medium text-neutral-600">E-Mail *</label>
                                    <input type="email" id="inquiry_email" name="email" required
                                           value="{{ old('email') }}"
                                           class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="inquiry_phone" class="block text-xs font-medium text-neutral-600">Telefon (optional)</label>
                                    <input type="text" id="inquiry_phone" name="phone"
                                           value="{{ old('phone') }}"
                                           class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="inquiry_message" class="block text-xs font-medium text-neutral-600">Ihre Nachricht *</label>
                                    <textarea id="inquiry_message" name="message" rows="3" required
                                              placeholder="z. B. Fragen zu Zustand, Besichtigung oder Inzahlungnahme …"
                                              class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">{{ old('message', $watch->isBuyableInShop() ? 'Ich interessiere mich für die '.$watch->fullName().'. Bitte kontaktieren Sie mich.' : 'Ich interessiere mich für eine vergleichbare Uhr wie die '.$watch->fullName().'. Bitte kontaktieren Sie mich.') }}</textarea>
                                    @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <button type="submit"
                                    class="mt-4 inline-flex items-center justify-center rounded-full bg-blue-800 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">
                                Anfrage senden
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Beschreibung --}}
                @if (filled($watch->description))
                    <div class="mt-10">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Beschreibung</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-neutral-700">{{ $watch->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Spezifikationen --}}
        <section class="mt-20">
            <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Technische Daten</h2>
            <div class="mt-6 grid grid-cols-1 gap-10 md:grid-cols-2">
                @foreach ($specGroups as $groupTitle => $rows)
                    @php $rows = array_filter($rows, fn ($value) => filled($value)); @endphp
                    @if ($rows !== [])
                        <div>
                            <h3 class="border-b border-neutral-200 pb-2 text-sm font-semibold text-blue-900">{{ $groupTitle }}</h3>
                            <dl class="divide-y divide-neutral-100">
                                @foreach ($rows as $label => $value)
                                    <div class="flex justify-between gap-6 py-2.5 text-sm">
                                        <dt class="shrink-0 text-neutral-500">{{ $label }}</dt>
                                        <dd class="text-right font-medium text-neutral-900">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        {{-- Weitere Uhren --}}
        @if ($related->isNotEmpty())
            <section class="mt-24">
                <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Das könnte Sie auch interessieren</h2>
                <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $relatedWatch)
                        @include('shop.partials.watch-card', ['watch' => $relatedWatch])
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Galerie-Wechsel: reiner src-Tausch, kein Framework nötig --}}
    @if (count($photos) > 1)
        <script>
            document.querySelectorAll('.shop-thumb').forEach((thumb) => {
                thumb.addEventListener('click', () => {
                    document.getElementById('shop-main-photo').src = thumb.dataset.photo;
                    document.querySelectorAll('.shop-thumb').forEach((other) => {
                        other.classList.remove('border-blue-800', 'ring-1', 'ring-blue-800');
                        other.classList.add('border-neutral-200');
                    });
                    thumb.classList.add('border-blue-800', 'ring-1', 'ring-blue-800');
                    thumb.classList.remove('border-neutral-200');
                });
            });
        </script>
    @endif
@endsection
