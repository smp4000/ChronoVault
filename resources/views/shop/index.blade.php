{{--
=============================================================================
Shop-Startseite — Produktgrid mit Markenfilter und Pagination
=============================================================================
Erwartet: $watches (Paginator), $brands (Collection), $activeBrandId.
Pagination bewusst selbst gerendert: die Vendor-Pagination-Views liegen
außerhalb des Tailwind-@source-Scans, ihre Klassen fehlen im Build.
=============================================================================
--}}
@extends('shop.layout')

@section('title', 'Kollektion')

@section('content')
    {{-- Erfolgsmeldung nach verbindlichem Kauf --}}
    @if (session('purchase_success'))
        <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-900">
                {{ session('purchase_success') }}
            </div>
        </div>
    @endif

    {{-- Hero: ruhig, viel Weißraum, blaue Akzentlinie --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 pb-10 sm:px-6 lg:px-8 lg:pt-24 lg:pb-14">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">Unsere Kollektion</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl lg:text-5xl">
                Ausgewählte Zeitmesser
            </h1>
            <p class="mt-4 text-base leading-relaxed text-neutral-500">
                Jede Uhr in unserer Kollektion ist geprüft und dokumentiert.
                Gerne beraten wir Sie persönlich zu jedem Stück.
            </p>
            <div class="mt-6 h-px w-16 bg-blue-800"></div>
        </div>
    </section>

    {{-- Markenfilter als Pill-Leiste (nur Marken, die im Shop vertreten sind) --}}
    @if ($brands->count() > 1)
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('shop.index', array_filter(array_merge($filters, ['marke' => null]))) }}"
                   class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $activeBrandId ? 'border-neutral-300 text-neutral-600 hover:border-blue-800 hover:text-blue-800' : 'border-blue-800 bg-blue-800 text-white' }}">
                    Alle
                </a>
                @foreach ($brands as $brand)
                    <a href="{{ route('shop.index', array_filter(array_merge($filters, ['marke' => $brand->id]))) }}"
                       class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $activeBrandId === $brand->id ? 'border-blue-800 bg-blue-800 text-white' : 'border-neutral-300 text-neutral-600 hover:border-blue-800 hover:text-blue-800' }}">
                        {{ $brand->name }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Filterleiste: Dropdowns (auto-submit) + Sortierung + Favoriten + Artikelzähler --}}
    @php
        $select = 'rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-700 focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800';
    @endphp
    <section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('shop.index') }}" class="flex flex-wrap items-center gap-3">
            @if ($activeBrandId)
                <input type="hidden" name="marke" value="{{ $activeBrandId }}">
            @endif

            <select name="zustand" onchange="this.form.submit()" class="{{ $select }}">
                <option value="">Zustand</option>
                @foreach (\App\Enums\WatchCondition::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['zustand'] === $case->value)>{{ $case->getLabel() }}</option>
                @endforeach
            </select>

            <select name="material" onchange="this.form.submit()" class="{{ $select }}">
                <option value="">Gehäusematerial</option>
                @foreach (\App\Enums\CaseMaterial::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['material'] === $case->value)>{{ $case->getLabel() }}</option>
                @endforeach
            </select>

            <select name="durchmesser" onchange="this.form.submit()" class="{{ $select }}">
                <option value="">Durchmesser</option>
                <option value="bis36" @selected($filters['durchmesser'] === 'bis36')>bis 36 mm</option>
                <option value="36-40" @selected($filters['durchmesser'] === '36-40')>36 – 40 mm</option>
                <option value="ab40" @selected($filters['durchmesser'] === 'ab40')>ab 40 mm</option>
            </select>

            <select name="preis" onchange="this.form.submit()" class="{{ $select }}">
                <option value="">Preis</option>
                <option value="bis1000" @selected($filters['preis'] === 'bis1000')>bis 1.000 €</option>
                <option value="1000-5000" @selected($filters['preis'] === '1000-5000')>1.000 – 5.000 €</option>
                <option value="5000-10000" @selected($filters['preis'] === '5000-10000')>5.000 – 10.000 €</option>
                <option value="ab10000" @selected($filters['preis'] === 'ab10000')>ab 10.000 €</option>
            </select>

            <select name="sortierung" onchange="this.form.submit()" class="{{ $select }}">
                <option value="neueste" @selected($filters['sortierung'] === 'neueste')>Neueste Artikel</option>
                <option value="preis_auf" @selected($filters['sortierung'] === 'preis_auf')>Preis aufsteigend</option>
                <option value="preis_ab" @selected($filters['sortierung'] === 'preis_ab')>Preis absteigend</option>
            </select>

            {{-- Merkliste: clientseitiger Filter (localStorage) --}}
            <button type="button" data-active="0"
                    class="cv-fav-filter inline-flex items-center gap-2 rounded-xl border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-blue-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                </svg>
                Favoriten (<span class="cv-fav-count">0</span>)
            </button>

            <span class="ml-auto text-sm text-neutral-500">{{ $watches->total() }} Artikel</span>
        </form>
    </section>

    {{-- Produktgrid --}}
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($watches->isEmpty())
            {{-- Empty-State: freundlich statt leerer Seite --}}
            <div class="flex flex-col items-center rounded-3xl border border-dashed border-neutral-300 px-6 py-24 text-center">
                <svg class="h-14 w-14 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <h2 class="mt-6 text-lg font-medium text-neutral-900">Derzeit keine Uhren verfügbar</h2>
                <p class="mt-2 max-w-sm text-sm text-neutral-500">
                    Unsere Kollektion wird laufend erweitert — schauen Sie bald wieder
                    vorbei oder kontaktieren Sie uns mit Ihrem Suchauftrag.
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($watches as $watch)
                    @include('shop.partials.watch-card', ['watch' => $watch])
                @endforeach
            </div>

            {{-- Pagination: schlicht, Vor/Zurück + Seitenzähler --}}
            @if ($watches->hasPages())
                <nav class="mt-16 flex items-center justify-center gap-6 text-sm" aria-label="Seiten">
                    @if ($watches->onFirstPage())
                        <span class="cursor-default text-neutral-300">&larr; Zurück</span>
                    @else
                        <a href="{{ $watches->previousPageUrl() }}" class="font-medium text-blue-800 transition hover:text-blue-600">&larr; Zurück</a>
                    @endif

                    <span class="text-neutral-500">Seite {{ $watches->currentPage() }} von {{ $watches->lastPage() }}</span>

                    @if ($watches->hasMorePages())
                        <a href="{{ $watches->nextPageUrl() }}" class="font-medium text-blue-800 transition hover:text-blue-600">Weiter &rarr;</a>
                    @else
                        <span class="cursor-default text-neutral-300">Weiter &rarr;</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>

    @include('shop.partials.favorites-script')
@endsection
