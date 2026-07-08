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
    $specParts = array_filter([
        $watch->reference_number ? 'Ref. '.$watch->reference_number : null,
        $watch->production_year ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year : null,
        $watch->case_diameter_mm ? rtrim(rtrim(number_format((float) $watch->case_diameter_mm, 1, ',', '.'), '0'), ',').' mm' : null,
    ]);
@endphp

<a href="{{ route('shop.show', $watch) }}" class="group block">
    <div class="aspect-square overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-50 transition group-hover:border-blue-200 group-hover:shadow-lg group-hover:shadow-blue-900/5">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}"
                 alt="{{ $watch->fullName() }}"
                 loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @else
            {{-- Eleganter Empty-State statt gebrochenem Bild --}}
            <div class="flex h-full w-full items-center justify-center">
                <svg class="h-12 w-12 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        @endif
    </div>

    <div class="mt-4 space-y-1">
        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-blue-800">
            {{ $watch->brand->name }}
        </p>
        <p class="font-medium text-neutral-900 transition group-hover:text-blue-900">
            {{ $watch->model_name }}
        </p>
        @if ($specParts !== [])
            <p class="text-xs text-neutral-500">{{ implode(' · ', $specParts) }}</p>
        @endif
        <p class="pt-1 text-sm">
            @if ($watch->formattedAskingPrice())
                <span class="font-semibold text-neutral-900">{{ $watch->formattedAskingPrice() }}</span>
            @else
                <span class="text-neutral-500">Preis auf Anfrage</span>
            @endif
        </p>
    </div>
</a>
