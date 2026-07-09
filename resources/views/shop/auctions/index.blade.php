{{--
=============================================================================
Auktionskatalog — Übersicht aller öffentlichen Auktionen (Modul 8b)
=============================================================================
Erwartet: $auctions (Collection mit lots_count; laufende zuerst).
=============================================================================
--}}
@extends('shop.layout')

@section('title', 'Auktionen')

@section('content')
    <section class="mx-auto max-w-7xl px-4 pt-16 pb-10 sm:px-6 lg:px-8 lg:pt-24 lg:pb-14">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">Versteigerungen</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl lg:text-5xl">
                Auktionen
            </h1>
            <p class="mt-4 text-base leading-relaxed text-neutral-500">
                Bei Online-Auktionen können Sie direkt hier bieten — bei
                Saalauktionen begrüßen wir Sie gerne vor Ort.
            </p>
            <div class="mt-6 h-px w-16 bg-blue-800"></div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-10 sm:px-6 lg:px-8">
        @if ($auctions->isEmpty())
            <div class="flex flex-col items-center rounded-3xl border border-dashed border-neutral-300 px-6 py-24 text-center">
                <svg class="h-14 w-14 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73" />
                </svg>
                <h2 class="mt-6 text-lg font-medium text-neutral-900">Derzeit keine Auktionen</h2>
                <p class="mt-2 max-w-sm text-sm text-neutral-500">
                    Sobald wir eine Versteigerung planen, finden Sie hier
                    Katalog und Termine.
                </p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($auctions as $auction)
                    <a href="{{ route('shop.auctions.show', $auction) }}"
                       class="group flex flex-col gap-4 rounded-2xl border border-neutral-200 p-6 transition hover:border-blue-200 hover:shadow-lg hover:shadow-blue-900/5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-lg font-semibold text-neutral-900 transition group-hover:text-blue-900">
                                    {{ $auction->title }}
                                </h2>
                                @if ($auction->status === App\Enums\AuctionStatus::Live)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-800 px-3 py-0.5 text-xs font-semibold text-white">
                                        <span class="block h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span>
                                        Läuft
                                    </span>
                                @elseif ($auction->status === App\Enums\AuctionStatus::Completed)
                                    <span class="rounded-full bg-neutral-100 px-3 py-0.5 text-xs font-medium text-neutral-600">Beendet</span>
                                @else
                                    <span class="rounded-full bg-blue-50 px-3 py-0.5 text-xs font-medium text-blue-900">Demnächst</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-neutral-500">
                                {{ $auction->venue->getLabel() }}
                                @if ($auction->location) · {{ $auction->location }} @endif
                                @if ($auction->starts_at) · {{ $auction->starts_at->format('d.m.Y H:i') }} Uhr @endif
                            </p>
                            @if ($auction->isBiddingOpen() && $auction->ends_at)
                                <div class="mt-3">
                                    @include('shop.partials.countdown', ['endsAt' => $auction->ends_at])
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="text-right">
                                <p class="text-2xl font-semibold text-blue-900">{{ $auction->lots_count }}</p>
                                <p class="text-xs text-neutral-500">{{ $auction->lots_count === 1 ? 'Los' : 'Lose' }}</p>
                            </div>
                            <span class="text-blue-800 transition group-hover:translate-x-1">&rarr;</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Live-Update: neu laden, wenn eine Auktion startet oder endet --}}
    @include('shop.partials.live-refresh', ['statusUrl' => $liveStatusUrl, 'fingerprint' => $liveFingerprint])
@endsection
