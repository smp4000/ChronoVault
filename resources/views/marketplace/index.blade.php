{{--
=============================================================================
Marktplatz-Startseite — Angebote aller Verkäufer (zentraler Spiegel)
=============================================================================
Erwartet: $listings (Paginator), $brands (Collection<string>), $search,
$filters. Kacheln verlinken in den Shop des Verkäufers (detail_url) —
dort laufen Kauf, Anfrage und Preisvorschlag.
=============================================================================
--}}
@extends('marketplace.layout')

@section('title', $search !== '' ? 'Suche: '.$search : 'Marktplatz')

@section('content')
    {{-- Kopf: Hero bzw. Suchkontext --}}
    @if ($search !== '')
        <section class="mx-auto max-w-7xl px-4 pt-10 pb-2 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">Suchergebnis</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">
                „{{ $search }}"
            </h1>
            <p class="mt-2 text-sm text-neutral-500">
                {{ $listings->total() }} Treffer auf dem Marktplatz
                · <a href="{{ route('marketplace.index') }}" class="font-medium text-blue-800 hover:text-blue-600">Suche zurücksetzen</a>
            </p>
        </section>
    @else
        <section class="mx-auto max-w-7xl px-4 pt-14 pb-8 sm:px-6 lg:px-8 lg:pt-20 lg:pb-12">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">Der Uhren-Marktplatz</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl lg:text-5xl">
                    Uhren von privaten und gewerblichen Verkäufern
                </h1>
                <p class="mt-4 text-base leading-relaxed text-neutral-500">
                    Jedes Angebot führt direkt in den Shop des Verkäufers — mit
                    Foto-Dokumentation, Sofortkauf, Anfrage und Preisvorschlag.
                </p>
                <div class="mt-6 h-px w-16 bg-blue-800"></div>
            </div>
        </section>
    @endif

    {{-- Markenfilter --}}
    @if ($brands->count() > 1)
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('marketplace.index', array_filter(array_merge($filters, ['marke' => null]))) }}"
                   class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $filters['marke'] ? 'border-neutral-300 text-neutral-600 hover:border-blue-800 hover:text-blue-800' : 'border-blue-800 bg-blue-800 text-white' }}">
                    Alle
                </a>
                @foreach ($brands as $brandName)
                    <a href="{{ route('marketplace.index', array_filter(array_merge($filters, ['marke' => $brandName]))) }}"
                       class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $filters['marke'] === $brandName ? 'border-blue-800 bg-blue-800 text-white' : 'border-neutral-300 text-neutral-600 hover:border-blue-800 hover:text-blue-800' }}">
                        {{ $brandName }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Filterleiste: Verkäufer-Typ + Sortierung + Zähler --}}
    @php
        $select = 'rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-700 focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800';
    @endphp
    <section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('marketplace.index') }}" class="flex flex-wrap items-center gap-3">
            @foreach (['marke', 'suche'] as $keep)
                @if ($filters[$keep])
                    <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
                @endif
            @endforeach

            <select name="verkaeufer" onchange="this.form.submit()" class="{{ $select }}">
                <option value="">Alle Verkäufer</option>
                <option value="commercial" @selected($filters['verkaeufer'] === 'commercial')>Gewerblich</option>
                <option value="private" @selected($filters['verkaeufer'] === 'private')>Privat</option>
            </select>

            <select name="sortierung" onchange="this.form.submit()" class="{{ $select }}">
                <option value="neueste" @selected($filters['sortierung'] === 'neueste')>Neueste Angebote</option>
                <option value="preis_auf" @selected($filters['sortierung'] === 'preis_auf')>Preis aufsteigend</option>
                <option value="preis_ab" @selected($filters['sortierung'] === 'preis_ab')>Preis absteigend</option>
            </select>

            <span class="ml-auto text-sm text-neutral-500">{{ $listings->total() }} {{ $listings->total() === 1 ? 'Angebot' : 'Angebote' }}</span>
        </form>
    </section>

    {{-- Angebots-Grid --}}
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($listings->isEmpty())
            <div class="flex flex-col items-center rounded-3xl border border-dashed border-neutral-300 px-6 py-24 text-center">
                <svg class="h-14 w-14 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @if ($search !== '')
                    <h2 class="mt-6 text-lg font-medium text-neutral-900">Keine Treffer für „{{ $search }}"</h2>
                    <p class="mt-2 max-w-sm text-sm text-neutral-500">
                        Versuchen Sie einen anderen Suchbegriff oder
                        <a href="{{ route('marketplace.index') }}" class="font-medium text-blue-800 hover:text-blue-600">sehen Sie alle Angebote an</a>.
                    </p>
                @else
                    <h2 class="mt-6 text-lg font-medium text-neutral-900">Derzeit keine Angebote</h2>
                    <p class="mt-2 max-w-sm text-sm text-neutral-500">
                        Der Marktplatz füllt sich laufend — schauen Sie bald wieder vorbei.
                    </p>
                @endif
            </div>
        @else
            <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 sm:gap-y-10 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($listings as $listing)
                    <a href="{{ $listing->detail_url }}" class="group block">
                        <div class="relative aspect-square overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-50 transition group-hover:border-blue-200 group-hover:shadow-lg group-hover:shadow-blue-900/5">
                            @if ($listing->photo_url)
                                <img src="{{ $listing->photo_url }}"
                                     alt="{{ $listing->brand_name }} {{ $listing->model_name }}"
                                     loading="lazy"
                                     class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <svg class="h-12 w-12 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                            @endif

                            @if ($listing->discount_percent !== null)
                                <span class="absolute left-3 top-3 rounded-md bg-red-600 px-2.5 py-1 text-[11px] font-bold tracking-wider text-white shadow-sm">
                                    &minus;{{ $listing->discount_percent }} %
                                </span>
                            @endif

                            {{-- Verkäufer-Typ (eBay-Prinzip) --}}
                            <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold tracking-wide text-neutral-900 shadow-sm ring-1 ring-black/5 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full {{ $listing->seller_type === 'private' ? 'bg-amber-500' : 'bg-blue-700' }}"></span>
                                {{ $listing->sellerTypeLabel() }}
                            </span>
                        </div>

                        <div class="mt-3 space-y-1">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-blue-800">
                                {{ $listing->brand_name }}
                            </p>
                            <p class="line-clamp-1 font-medium text-neutral-900 transition group-hover:text-blue-900">
                                {{ $listing->model_name }}
                            </p>
                            @php
                                $specParts = array_filter([
                                    $listing->reference_number ? 'Ref. '.$listing->reference_number : null,
                                    $listing->year_label,
                                    $listing->diameter_label,
                                ]);
                            @endphp
                            @if ($specParts !== [])
                                <p class="line-clamp-1 text-xs text-neutral-500">{{ implode(' · ', $specParts) }}</p>
                            @endif

                            @if ($listing->condition_label || $listing->has_box || $listing->has_papers)
                                <div class="flex flex-wrap items-center gap-1 pt-0.5">
                                    @if ($listing->condition_label)
                                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">{{ $listing->condition_label }}</span>
                                    @endif
                                    @if ($listing->has_box)
                                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">Box</span>
                                    @endif
                                    @if ($listing->has_papers)
                                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">Papiere</span>
                                    @endif
                                </div>
                            @endif

                            <p class="pt-1.5">
                                @if ($listing->formattedPrice())
                                    <span class="text-base font-bold {{ $listing->discount_percent !== null ? 'text-red-600' : 'text-neutral-900' }}">{{ $listing->formattedPrice() }}</span>
                                    @if ($listing->discount_percent !== null && $listing->formattedPreviousPrice())
                                        <span class="ml-1 text-xs text-neutral-400 line-through">{{ $listing->formattedPreviousPrice() }}</span>
                                    @endif
                                @else
                                    <span class="text-sm font-medium text-neutral-500">Preis auf Anfrage</span>
                                @endif
                            </p>

                            <p class="text-xs text-neutral-400">bei {{ $listing->seller_name }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($listings->hasPages())
                <nav class="mt-16 flex items-center justify-center gap-6 text-sm" aria-label="Seiten">
                    @if ($listings->onFirstPage())
                        <span class="cursor-default text-neutral-300">&larr; Zurück</span>
                    @else
                        <a href="{{ $listings->previousPageUrl() }}" class="font-medium text-blue-800 transition hover:text-blue-600">&larr; Zurück</a>
                    @endif

                    <span class="text-neutral-500">Seite {{ $listings->currentPage() }} von {{ $listings->lastPage() }}</span>

                    @if ($listings->hasMorePages())
                        <a href="{{ $listings->nextPageUrl() }}" class="font-medium text-blue-800 transition hover:text-blue-600">Weiter &rarr;</a>
                    @else
                        <span class="cursor-default text-neutral-300">Weiter &rarr;</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
