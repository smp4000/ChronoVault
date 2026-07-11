{{--
=============================================================================
Shop-Partial: Uhren-Kachel (Produktgrid)
=============================================================================
Aufbau nach Design-Referenz: Bild → Marke → Modell → Specs → Preis.
Erwartet: $watch (mit geladenen brand/media-Relationen).
=============================================================================
--}}
@php
    $photoUrl = $watch->firstPhotoUrl();
    $statusBadge = $watch->shopStatusBadge();
    // Rabatt-Badge nur für kaufbare Uhren mit aktiver Preissenkung
    $discount = $watch->isBuyableInShop() ? $watch->discountPercent() : null;
    // "Neu"-Badge für frisch eingestellte Uhren (14 Tage) — Rabatt hat Vorrang
    $isNew = $discount === null && $watch->created_at !== null && $watch->created_at->gt(now()->subDays(14));
    $specParts = array_filter([
        $watch->reference_number ? 'Ref. '.$watch->reference_number : null,
        $watch->production_year ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year : null,
        $watch->case_diameter_mm ? rtrim(rtrim(number_format((float) $watch->case_diameter_mm, 1, ',', '.'), '0'), ',').' mm' : null,
    ]);
@endphp

<a href="{{ route('shop.show', $watch) }}" class="cv-card group block" data-watch="{{ $watch->getKey() }}">
    <div class="relative aspect-square overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-50 transition group-hover:border-blue-200 group-hover:shadow-lg group-hover:shadow-blue-900/5">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}"
                 alt="{{ $watch->fullName() }}"
                 loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03] {{ $statusBadge === 'Verkauft' ? 'opacity-80 saturate-[0.85]' : '' }}">
        @else
            {{-- Eleganter Empty-State statt gebrochenem Bild --}}
            <div class="flex h-full w-full items-center justify-center">
                <svg class="h-12 w-12 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        @endif

        {{-- Status-Badge (Verkauft/Reserviert/In Auktion) — weißer Pill wie beim Design-Vorbild --}}
        @if ($statusBadge)
            <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold tracking-wide text-neutral-900 shadow-sm ring-1 ring-black/5 backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full {{ $statusBadge === 'Verkauft' ? 'bg-neutral-400' : ($statusBadge === 'Reserviert' ? 'bg-amber-500' : 'bg-blue-700') }}"></span>
                {{ $statusBadge }}
            </span>
        @endif

        {{-- Rabatt-Badge (Preissenkung) bzw. "Neu"-Badge oben links --}}
        @if ($discount !== null)
            <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-md bg-red-600 px-2.5 py-1 text-[11px] font-bold tracking-wider text-white shadow-sm">
                &minus;{{ $discount }} %
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
            </span>
        @elseif ($isNew)
            <span class="absolute left-3 top-3 rounded-md bg-blue-800 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-white shadow-sm">
                Neu
            </span>
        @endif

        {{-- Favoriten-Herz oben rechts (localStorage, kein Konto nötig) --}}
        <button type="button"
                class="cv-fav absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-neutral-400 shadow-sm ring-1 ring-black/5 backdrop-blur transition hover:text-red-500"
                data-watch="{{ $watch->getKey() }}"
                aria-label="Zur Merkliste hinzufügen">
            <svg class="cv-fav-icon h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>
        </button>
    </div>

    <div class="mt-3 space-y-1">
        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-blue-800">
            {{ $watch->brand->name }}
        </p>
        <p class="line-clamp-1 font-medium text-neutral-900 transition group-hover:text-blue-900">
            {{ $watch->model_name }}
        </p>
        @if ($specParts !== [])
            <p class="line-clamp-1 text-xs text-neutral-500">{{ implode(' · ', $specParts) }}</p>
        @endif

        {{-- Ausstattungs-Chips (Chrono24-Stil): Zustand, Box, Papiere --}}
        @php
            $condition = $watch->condition;
        @endphp
        @if ($condition || $watch->has_box || $watch->has_papers)
            <div class="flex flex-wrap items-center gap-1 pt-0.5">
                @if ($condition)
                    <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">{{ $condition->getLabel() }}</span>
                @endif
                @if ($watch->has_box)
                    <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">Box</span>
                @endif
                @if ($watch->has_papers)
                    <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">Papiere</span>
                @endif
            </div>
        @endif

        <p class="pt-1.5">
            @if ($watch->formattedAskingPrice())
                <span class="text-base font-bold {{ $discount !== null ? 'text-red-600' : 'text-neutral-900' }}">{{ $watch->formattedAskingPrice() }}</span>
                @if ($discount !== null)
                    <span class="ml-1 text-xs text-neutral-400 line-through">{{ $watch->formattedPreviousPrice() }}</span>
                @endif
            @else
                <span class="text-sm font-medium text-neutral-500">Preis auf Anfrage</span>
            @endif
        </p>

        @if ($watch->isBuyableInShop())
            <p class="flex items-center gap-1.5 text-xs text-green-700">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                Sofort lieferbar
            </p>
        @endif
    </div>
</a>
