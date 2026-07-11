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

    // Steuerhinweis je Besteuerungsart des Betriebs (Betriebsdaten-Seite)
    $taxNote = match ((string) (tenant('tax_mode') ?? 'differential')) {
        'regular' => 'inkl. 19 % MwSt., zzgl. Versand',
        'small_business' => 'Keine MwSt. ausweisbar (§ 19 UStG), zzgl. Versand',
        default => 'nach § 25a UStG differenzbesteuert — keine MwSt. ausweisbar, zzgl. Versand',
    };

    // Rechenfrage für den Preisvorschlag (Spam-Schutz)
    $capA = random_int(1, 9);
    $capB = random_int(1, 9);

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
                <div class="mt-2 flex items-start justify-between gap-4">
                    <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        {{ $watch->model_name }}
                    </h1>
                    {{-- Favoriten-Herz (localStorage-Merkliste) --}}
                    <button type="button"
                            class="cv-fav mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-neutral-400 transition hover:text-red-500"
                            data-watch="{{ $watch->getKey() }}"
                            aria-label="Zur Merkliste hinzufügen">
                        <svg class="cv-fav-icon h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                        </svg>
                    </button>
                </div>
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
                        @if ($watch->discountPercent() !== null)
                            {{-- Preissenkung: roter Preis, Streichpreis, Ersparnis + 30-Tage-Hinweis (PAngV) --}}
                            <p class="flex flex-wrap items-baseline gap-3">
                                <span class="text-3xl font-semibold text-red-600">{{ $watch->formattedAskingPrice() }}</span>
                                <span class="text-xl text-neutral-400 line-through decoration-1">{{ $watch->formattedPreviousPrice() }}</span>
                                <span class="rounded-md bg-red-600 px-2 py-0.5 text-xs font-bold text-white">&minus;{{ $watch->discountPercent() }} %</span>
                            </p>
                            <p class="mt-1 text-sm font-medium text-neutral-700">
                                Sie sparen {{ number_format((float) $watch->previous_asking_price - (float) $watch->asking_price, 2, ',', '.') }} €
                            </p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                Preis der letzten 30 Tage vor Preissenkung: {{ $watch->formattedPreviousPrice() }}
                            </p>
                            <p class="mt-1 text-xs text-neutral-400">{{ $taxNote }}</p>
                        @else
                            <p class="text-3xl font-semibold text-blue-900">{{ $watch->formattedAskingPrice() }}</p>
                            <p class="mt-1 text-xs text-neutral-400">{{ $taxNote }}</p>
                        @endif
                        <p class="mt-2 flex items-center gap-1.5 text-sm text-green-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                            Sofort lieferbar in 1–2 Werktagen nach Zahlungseingang
                        </p>

                        <a href="{{ route('shop.buy', $watch) }}"
                           class="mt-4 inline-flex items-center justify-center rounded-full bg-blue-800 px-8 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                            Jetzt verbindlich kaufen
                        </a>
                    @else
                        <p class="text-2xl font-medium text-neutral-700">Preis auf Anfrage</p>
                    @endif

                    {{-- Aktionen: Teilen / Frage stellen / Preis vorschlagen --}}
                    <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-neutral-600">
                        <button type="button" onclick="cvOpenModal('cv-share-modal')"
                                class="inline-flex items-center gap-1.5 transition hover:text-blue-800">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" /></svg>
                            Teilen
                        </button>
                        <a href="#anfrage" class="inline-flex items-center gap-1.5 transition hover:text-blue-800">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" /></svg>
                            Frage zum Artikel stellen
                        </a>
                        @if ($watch->isBuyableInShop())
                            <button type="button" onclick="cvOpenModal('cv-propose-modal')"
                                    class="inline-flex items-center gap-1.5 transition hover:text-blue-800">
                                <span class="text-base leading-none">€</span>
                                Preis vorschlagen
                            </button>
                        @endif
                    </div>

                    @if (session('proposal_success'))
                        <div class="mt-4 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-900">
                            {{ session('proposal_success') }}
                        </div>
                    @endif

                    @error('purchase')
                        <p class="mt-3 rounded-xl bg-red-50 px-4 py-2.5 text-sm text-red-900">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Schnell-Merkmale als Chips --}}
                <div class="mt-6 flex flex-wrap gap-2">
                    @if ($watch->condition)
                        <button type="button" onclick="cvOpenModal('cv-condition-modal')"
                                class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-900 transition hover:bg-blue-100"
                                title="Was bedeutet der Zustand?">
                            {{ $watch->condition->getLabel() }}
                            <svg class="h-3.5 w-3.5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        </button>
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
                    <div id="anfrage" class="mt-8 rounded-2xl border border-blue-100 bg-blue-50/50 p-6">
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

                            <p class="mt-3 text-xs leading-relaxed text-neutral-400">
                                Mit dem Absenden stimmen Sie der Verarbeitung Ihrer Angaben zur
                                Beantwortung der Anfrage zu — Details in unserer
                                <a href="{{ route('shop.legal.privacy') }}" class="underline hover:text-blue-800">Datenschutzerklärung</a>.
                            </p>
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

            {{-- Hinweis Wasserdichtigkeit (gebrauchte Uhren) --}}
            <div class="mt-10">
                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Wasserdichtigkeit</h3>
                <div class="mt-3 rounded-2xl border border-blue-100 bg-blue-50/60 px-5 py-4 text-xs leading-relaxed text-neutral-700">
                    <strong class="text-blue-900">Keine Garantie auf Wasserdichtigkeit bei gebrauchten Uhren!</strong>
                    Bitte beachten Sie, dass die Wasserdichtigkeit lediglich zum Zeitpunkt einer geprüften
                    Kontrolle gewährleistet werden kann. Sie kann durch äußere Einflüsse wie Stöße,
                    Temperaturschwankungen, Fette, Säuren oder unsachgemäßen Gebrauch (z.&nbsp;B. das Öffnen
                    der Krone oder das Betätigen von Drückern unter Wasser) beeinträchtigt werden. Aus diesem
                    Grund übernehmen wir eine Garantie auf Wasserdichtigkeit ausschließlich bei Vorlage eines
                    entsprechenden Prüfprotokolls, das nicht älter als 14 Tage ist.
                </div>
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

    {{-- Zustand-Erklärung: unsere Zustandsgruppen --}}
    <div id="cv-condition-modal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/40 p-4 pt-24">
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-neutral-900">Zustand</h2>
                <button type="button" onclick="cvCloseModal('cv-condition-modal')"
                        class="text-2xl leading-none text-neutral-400 transition hover:text-neutral-700" aria-label="Schließen">&times;</button>
            </div>
            <p class="mt-4 text-sm leading-relaxed text-neutral-600">
                Um den allgemeinen Zustand einer Uhr für Sie so transparent wie möglich
                darzustellen, stufen wir jede Uhr in eine Zustandsgruppe ein:
            </p>
            <ul class="mt-4 space-y-1.5 text-sm text-neutral-800">
                @foreach (\App\Enums\WatchCondition::cases() as $conditionCase)
                    <li class="flex items-center gap-2 {{ $watch->condition === $conditionCase ? 'font-semibold text-blue-900' : '' }}">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $watch->condition === $conditionCase ? 'bg-blue-700' : 'bg-neutral-300' }}"></span>
                        {{ $conditionCase->getLabel() }}
                        @if ($watch->condition === $conditionCase)
                            <span class="text-xs font-normal text-blue-700">(diese Uhr)</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Teilen-Dialog: Link kopieren oder per E-Mail teilen --}}
    <div id="cv-share-modal" class="fixed inset-0 z-50 hidden items-start justify-center bg-black/40 p-4 pt-24">
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-neutral-900">Teilen</h2>
                <button type="button" onclick="cvCloseModal('cv-share-modal')"
                        class="text-2xl leading-none text-neutral-400 transition hover:text-neutral-700" aria-label="Schließen">&times;</button>
            </div>
            <input type="text" readonly id="cv-share-url" value="{{ route('shop.show', $watch) }}"
                   class="mt-4 w-full truncate rounded-xl border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm text-neutral-700">
            <button type="button" id="cv-share-copy"
                    class="mt-3 w-full rounded-xl bg-blue-800 px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-blue-700">
                In Zwischenablage kopieren
            </button>
            <a href="mailto:?subject={{ rawurlencode($watch->fullName()) }}&body={{ rawurlencode('Schau dir diese Uhr an: '.route('shop.show', $watch)) }}"
               class="mt-2 block w-full rounded-xl border border-blue-800 px-4 py-2.5 text-center text-sm font-medium text-blue-800 transition hover:bg-blue-50">
                per E-Mail teilen
            </a>
        </div>
    </div>

    @if ($watch->isBuyableInShop())
        {{-- Preisvorschlag-Dialog --}}
        {{-- Bei Validierungsfehlern des Vorschlags direkt geöffnet (nur vorschlagsspezifische Felder prüfen) --}}
        <div id="cv-propose-modal" class="fixed inset-0 z-50 {{ $errors->hasAny(['proposed_price', 'captcha', 'privacy']) ? 'flex' : 'hidden' }} items-start justify-center overflow-y-auto bg-black/40 p-4 pt-16">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl" onclick="event.stopPropagation()">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-neutral-900">Wunschpreis angeben &amp; absenden</h2>
                    <button type="button" onclick="cvCloseModal('cv-propose-modal')"
                            class="text-2xl leading-none text-neutral-400 transition hover:text-neutral-700" aria-label="Schließen">&times;</button>
                </div>

                <form method="POST" action="{{ route('shop.propose', $watch) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="captcha_a" value="{{ $capA }}">
                    <input type="hidden" name="captcha_b" value="{{ $capB }}">

                    <div>
                        <label for="propose_price" class="block text-sm font-medium text-neutral-700">Preisvorschlag *</label>
                        <input type="number" id="propose_price" name="proposed_price" required min="1" step="1"
                               value="{{ old('proposed_price') }}" placeholder="Ihr Preisvorschlag in €"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('proposed_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_name" class="block text-sm font-medium text-neutral-700">Name *</label>
                        <input type="text" id="propose_name" name="name" required value="{{ old('name') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_email" class="block text-sm font-medium text-neutral-700">E-Mail-Adresse *</label>
                        <input type="email" id="propose_email" name="email" required value="{{ old('email') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_captcha" class="block text-sm font-medium text-neutral-700">Sicherheitsfrage *</label>
                        <input type="number" id="propose_captcha" name="captcha" required
                               placeholder="Bitte rechnen Sie {{ $capA }} + {{ $capB }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('captcha') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_message" class="block text-sm font-medium text-neutral-700">Nachricht</label>
                        <textarea id="propose_message" name="message" rows="3" placeholder="Nachricht schreiben"
                                  class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">{{ old('message') }}</textarea>
                        @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-start gap-3 text-xs leading-relaxed text-neutral-600">
                        <input type="checkbox" name="privacy" value="1" required class="mt-0.5 rounded border-neutral-300 text-blue-800 focus:ring-blue-800">
                        <span>
                            Ich stimme zu, dass meine Angaben aus dem Formular zur Beantwortung meiner
                            Anfrage erhoben und verarbeitet werden. Die Daten werden nach abgeschlossener
                            Bearbeitung Ihrer Anfrage gelöscht.*
                        </span>
                    </label>
                    @error('privacy') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <button type="submit"
                            class="w-full rounded-xl bg-blue-800 px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-blue-700">
                        Versenden
                    </button>
                </form>
            </div>
        </div>
    @endif

    <script>
        // Modals öffnen/schließen über hidden/flex-Klassen; Klick auf den
        // abgedunkelten Hintergrund schließt (Klicks im Dialog stoppen oben).
        function cvOpenModal(id) {
            var modal = document.getElementById(id);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function cvCloseModal(id) {
            var modal = document.getElementById(id);
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        ['cv-share-modal', 'cv-propose-modal', 'cv-condition-modal'].forEach(function (id) {
            var modal = document.getElementById(id);

            if (modal) {
                modal.addEventListener('click', function () { cvCloseModal(id); });
            }
        });

        // Link kopieren (mit Fallback für ältere Browser)
        var copyBtn = document.getElementById('cv-share-copy');

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var input = document.getElementById('cv-share-url');

                (navigator.clipboard
                    ? navigator.clipboard.writeText(input.value)
                    : Promise.reject()
                ).catch(function () {
                    input.select();
                    document.execCommand('copy');
                }).finally(function () {
                    copyBtn.textContent = 'Kopiert!';
                    setTimeout(function () { copyBtn.textContent = 'In Zwischenablage kopieren'; }, 1500);
                });
            });
        }
    </script>

    @include('shop.partials.favorites-script')

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
