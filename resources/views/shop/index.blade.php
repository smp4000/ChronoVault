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
                <a href="{{ route('shop.index') }}"
                   class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $activeBrandId ? 'border-neutral-300 text-neutral-600 hover:border-blue-800 hover:text-blue-800' : 'border-blue-800 bg-blue-800 text-white' }}">
                    Alle
                </a>
                @foreach ($brands as $brand)
                    <a href="{{ route('shop.index', ['marke' => $brand->id]) }}"
                       class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $activeBrandId === $brand->id ? 'border-blue-800 bg-blue-800 text-white' : 'border-neutral-300 text-neutral-600 hover:border-blue-800 hover:text-blue-800' }}">
                        {{ $brand->name }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

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
@endsection
