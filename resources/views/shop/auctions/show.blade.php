{{--
=============================================================================
Auktionsseite — alle Lose mit Uhr, Schätzpreis und Höchstgebot (Modul 8b)
=============================================================================
Erwartet: $auction, $lots (mit watch.brand/media, bids_count, bids_max_amount).
Bieternamen erscheinen NIE öffentlich — nur Höchstgebot und Anzahl.
=============================================================================
--}}
@extends('shop.layout')

@section('title', $auction->title)

@php
    use App\Enums\AuctionLotStatus;
    use App\Enums\AuctionStatus;

    $formatEur = fn ($value): string => number_format((float) $value, 0, ',', '.').' €';
    $biddingOpen = $auction->isBiddingOpen();
@endphp

@section('content')
    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8 lg:pt-12">

        <a href="{{ route('shop.auctions.index') }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 transition hover:text-blue-800">
            &larr; Alle Auktionen
        </a>

        {{-- Kopf: Titel, Status, Eckdaten --}}
        <div class="mt-8 max-w-3xl">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                    {{ $auction->title }}
                </h1>
                @if ($auction->status === AuctionStatus::Live)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-800 px-3 py-1 text-xs font-semibold text-white">
                        <span class="block h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span>
                        Läuft
                    </span>
                @elseif ($auction->status === AuctionStatus::Completed)
                    <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-600">Beendet</span>
                @else
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-900">Demnächst</span>
                @endif
            </div>

            <p class="mt-3 text-sm text-neutral-500">
                {{ $auction->venue->getLabel() }}
                @if ($auction->location) · {{ $auction->location }} @endif
                @if ($auction->starts_at) · Beginn {{ $auction->starts_at->format('d.m.Y H:i') }} Uhr @endif
                @if ($auction->ends_at) · Ende {{ $auction->ends_at->format('d.m.Y H:i') }} Uhr @endif
            </p>

            @if ($biddingOpen && $auction->ends_at)
                <div class="mt-4">
                    @include('shop.partials.countdown', ['endsAt' => $auction->ends_at])
                </div>
            @endif

            @if ($biddingOpen)
                <p class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    Diese Auktion läuft — Sie können auf jedes offene Los direkt online bieten.
                </p>
            @elseif ($auction->status === AuctionStatus::Scheduled && $auction->allowsOnlineBidding())
                <p class="mt-4 rounded-xl bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
                    Online-Gebote sind möglich, sobald die Auktion startet.
                </p>
            @endif

            @if (filled($auction->description))
                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-neutral-600">{{ $auction->description }}</p>
            @endif
        </div>

        {{-- Lose --}}
        <section class="mt-12 pb-4">
            <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Katalog</h2>

            @if ($lots->isEmpty())
                <p class="mt-6 text-sm text-neutral-500">Der Katalog wird derzeit zusammengestellt.</p>
            @else
                <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($lots as $lot)
                        <a href="{{ route('shop.auctions.lot', [$auction, $lot]) }}" class="group block">
                            <div class="relative aspect-square overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-50 transition group-hover:border-blue-200 group-hover:shadow-lg group-hover:shadow-blue-900/5">
                                <span class="absolute left-3 top-3 z-10 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-neutral-900 shadow-sm">
                                    Los {{ $lot->lot_number }}
                                </span>
                                @if ($lot->status === AuctionLotStatus::Sold)
                                    <span class="absolute right-3 top-3 z-10 rounded-full bg-blue-800 px-3 py-1 text-xs font-semibold text-white">Zugeschlagen</span>
                                @elseif ($lot->status !== AuctionLotStatus::Open)
                                    <span class="absolute right-3 top-3 z-10 rounded-full bg-neutral-200 px-3 py-1 text-xs font-medium text-neutral-600">{{ $lot->status->getLabel() }}</span>
                                @endif

                                @if ($lot->watch->firstPhotoUrl())
                                    <img src="{{ $lot->watch->firstPhotoUrl() }}"
                                         alt="{{ $lot->watch->fullName() }}"
                                         loading="lazy"
                                         class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                                @else
                                    <div class="flex h-full w-full items-center justify-center">
                                        <svg class="h-12 w-12 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-4 space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-blue-800">
                                    {{ $lot->watch->brand->name }}
                                </p>
                                <p class="font-medium text-neutral-900 transition group-hover:text-blue-900">
                                    {{ $lot->watch->model_name }}
                                </p>
                                @if ($lot->estimate_low !== null || $lot->estimate_high !== null)
                                    <p class="text-xs text-neutral-500">
                                        Schätzpreis:
                                        {{ $lot->estimate_low !== null ? $formatEur($lot->estimate_low) : '' }}{{ $lot->estimate_low !== null && $lot->estimate_high !== null ? ' – ' : '' }}{{ $lot->estimate_high !== null ? $formatEur($lot->estimate_high) : '' }}
                                    </p>
                                @endif
                                <p class="pt-1 text-sm">
                                    @if ($lot->status === AuctionLotStatus::Sold && $lot->hammer_price !== null)
                                        <span class="font-semibold text-neutral-900">Zuschlag: {{ $formatEur($lot->hammer_price) }}</span>
                                    @elseif ($lot->bids_max_amount !== null)
                                        <span class="font-semibold text-blue-900">Gebot: {{ $formatEur($lot->bids_max_amount) }}</span>
                                        <span class="text-neutral-400">({{ $lot->bids_count }})</span>
                                    @elseif ($lot->starting_price !== null)
                                        <span class="text-neutral-600">Aufruf: {{ $formatEur($lot->starting_price) }}</span>
                                    @else
                                        <span class="text-neutral-500">Noch kein Gebot</span>
                                    @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
