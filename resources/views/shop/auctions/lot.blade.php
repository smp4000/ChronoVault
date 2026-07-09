{{--
=============================================================================
Los-Detailseite — Galerie, Uhrdaten und Online-Gebotsformular (Modul 8b)
=============================================================================
Erwartet: $auction, $lot (mit watch.brand/caliber/media, bids_count).
Das Mindestgebot kommt aus AuctionLot::minimumNextBid(); fachliche
Ablehnungen der PlaceBidAction erscheinen als Fehler am Betragsfeld.
=============================================================================
--}}
@extends('shop.layout')

@section('title', 'Los '.$lot->lot_code.' — '.$lot->watch->fullName())

@php
    use App\Enums\AuctionLotStatus;

    $watch = $lot->watch;
    $photos = $watch->photoUrls();
    $formatEur = fn ($value): string => number_format((float) $value, 0, ',', '.').' €';

    $biddingOpen = $auction->isBiddingOpen() && $lot->isOpen();
    $highest = $lot->highestBidAmount();
    $minimum = $lot->minimumNextBid();
@endphp

@section('content')
    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8 lg:pt-12">

        <a href="{{ route('shop.auctions.show', $auction) }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 transition hover:text-blue-800">
            &larr; Zum Katalog „{{ $auction->title }}"
        </a>

        <div class="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">

            {{-- Galerie --}}
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

            {{-- Losdaten + Gebot --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">
                    Los {{ $lot->lot_code }} · {{ $watch->brand->name }}
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                    {{ $watch->model_name }}
                </h1>
                @if ($watch->reference_number)
                    <p class="mt-2 text-sm text-neutral-500">Referenz {{ $watch->reference_number }}</p>
                @endif

                {{-- Preis-/Gebotsstand --}}
                <dl class="mt-6 grid grid-cols-2 gap-4">
                    @if ($lot->estimate_low !== null || $lot->estimate_high !== null)
                        <div class="rounded-2xl border border-neutral-200 p-4">
                            <dt class="text-xs text-neutral-500">Schätzpreis</dt>
                            <dd class="mt-1 font-semibold text-neutral-900">
                                {{ $lot->estimate_low !== null ? $formatEur($lot->estimate_low) : '' }}{{ $lot->estimate_low !== null && $lot->estimate_high !== null ? ' – ' : '' }}{{ $lot->estimate_high !== null ? $formatEur($lot->estimate_high) : '' }}
                            </dd>
                        </div>
                    @endif

                    {{-- Gebotsstand — bewusst KEIN Limit-Hinweis und KEIN
                         Zuschlag-Ergebnis auf der Seite (nur per Mail an
                         die Bieter) --}}
                    <div class="rounded-2xl border border-blue-200 bg-blue-50/50 p-4">
                        <dt class="text-xs text-blue-900">
                            {{ $lot->isOpen() ? ($highest !== null ? 'Aktuelles Gebot' : 'Aufruf') : 'Letztes Gebot' }}
                        </dt>
                        <dd class="mt-1 text-xl font-semibold text-blue-900">
                            {{ $highest !== null ? $formatEur($highest) : ($lot->starting_price !== null ? $formatEur($lot->starting_price) : '—') }}
                        </dd>
                        <dd class="mt-0.5 text-xs text-blue-900/70">
                            {{ $lot->bids_count }} {{ $lot->bids_count === 1 ? 'Gebot' : 'Gebote' }}
                        </dd>
                    </div>
                </dl>

                {{-- Live-Countdown bis zum Auktionsende --}}
                @if ($biddingOpen && $auction->ends_at)
                    <div class="mt-6">
                        @include('shop.partials.countdown', ['endsAt' => $auction->ends_at])
                    </div>
                @endif

                {{-- Gebotsformular / Statushinweis --}}
                @if (session('bid_success'))
                    <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                        {{ session('bid_success') }} Das Auktionshaus meldet sich bei Ihnen, wenn Sie den Zuschlag erhalten.
                    </div>
                @endif

                @if ($biddingOpen)
                    <form method="POST"
                          action="{{ route('shop.auctions.bid', [$auction, $lot]) }}"
                          class="mt-6 rounded-2xl border border-neutral-200 p-6">
                        @csrf
                        <p class="font-medium text-neutral-900">Ihr Gebot</p>
                        <p class="mt-1 text-sm text-neutral-500">
                            Mindestgebot: <span class="font-semibold text-blue-900">{{ $formatEur($minimum) }}</span>
                            <span class="text-neutral-400">· Erhöhung um mind. {{ $formatEur($lot->bidIncrement()) }}, Betrag frei wählbar</span>
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="bidder_name" class="block text-xs font-medium text-neutral-600">Name *</label>
                                <input type="text" id="bidder_name" name="bidder_name" required
                                       value="{{ old('bidder_name') }}"
                                       class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                @error('bidder_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="bidder_email" class="block text-xs font-medium text-neutral-600">E-Mail *</label>
                                <input type="email" id="bidder_email" name="bidder_email" required
                                       value="{{ old('bidder_email') }}"
                                       class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                @error('bidder_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="bidder_phone" class="block text-xs font-medium text-neutral-600">Telefon</label>
                                <input type="text" id="bidder_phone" name="bidder_phone"
                                       value="{{ old('bidder_phone') }}"
                                       class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                            </div>
                            <div>
                                <label for="amount" class="block text-xs font-medium text-neutral-600">Gebot in € *</label>
                                {{-- Bewusst NICHT vorbefüllt: nach der Abgabe
                                     ist das Feld leer (nur bei Fehlern bleibt
                                     die Eingabe via old() stehen). --}}
                                <input type="number" id="amount" name="amount" required
                                       min="{{ (int) ceil($minimum) }}" step="1"
                                       value="{{ old('amount') }}"
                                       placeholder="mind. {{ (int) ceil($minimum) }}"
                                       class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <button type="submit"
                                class="mt-5 inline-flex w-full items-center justify-center rounded-full bg-blue-800 px-6 py-3 text-sm font-medium text-white transition hover:bg-blue-700 sm:w-auto">
                            Gebot abgeben
                        </button>
                        <p class="mt-3 text-xs text-neutral-400">
                            Mit der Abgabe geben Sie ein verbindliches Gebot ab. Ihre Daten
                            sind nur für das Auktionshaus sichtbar.
                        </p>
                    </form>
                @elseif (! $lot->isOpen())
                    {{-- Neutral: kein Ergebnis (Zuschlag/Rückgang) verraten --}}
                    <div class="mt-6 rounded-2xl bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
                        Für dieses Los können keine Gebote mehr abgegeben werden.
                    </div>
                @elseif ($auction->allowsOnlineBidding())
                    <div class="mt-6 rounded-2xl bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
                        Das Bietfenster ist derzeit geschlossen
                        @if ($auction->starts_at && $auction->starts_at->isFuture())
                            — die Auktion startet am {{ $auction->starts_at->format('d.m.Y \u\m H:i') }} Uhr.
                        @endif
                    </div>
                @else
                    <div class="mt-6 rounded-2xl bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
                        Saalauktion — Gebote sind vor Ort
                        @if ($auction->location) in {{ $auction->location }} @endif
                        möglich.
                    </div>
                @endif

                {{-- Kurzdaten der Uhr --}}
                @php
                    $quickSpecs = array_filter([
                        'Zustand' => $watch->condition?->getLabel(),
                        'Baujahr' => $watch->production_year
                            ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year
                            : null,
                        'Aufzug' => $watch->movement_type?->getLabel(),
                        'Kaliber' => $watch->caliber?->name,
                        'Gehäuse' => $watch->case_material?->getLabel(),
                        'Durchmesser' => $watch->case_diameter_mm
                            ? rtrim(rtrim(number_format((float) $watch->case_diameter_mm, 1, ',', '.'), '0'), ',').' mm'
                            : null,
                        'Lieferumfang' => implode(', ', array_filter([
                            $watch->has_box ? 'Originalbox' : null,
                            $watch->has_papers ? 'Papiere' : null,
                        ])) ?: null,
                    ], fn ($value) => filled($value));
                @endphp

                @if ($quickSpecs !== [])
                    <div class="mt-10">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Details</h2>
                        <dl class="mt-3 divide-y divide-neutral-100">
                            @foreach ($quickSpecs as $label => $value)
                                <div class="flex justify-between gap-6 py-2.5 text-sm">
                                    <dt class="shrink-0 text-neutral-500">{{ $label }}</dt>
                                    <dd class="text-right font-medium text-neutral-900">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                @if (filled($watch->description))
                    <div class="mt-8">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Beschreibung</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-neutral-700">{{ $watch->description }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Live-Update: neu laden bei Start/Ende/neuem Gebot (pausiert beim Tippen) --}}
    @include('shop.partials.live-refresh', ['statusUrl' => $liveStatusUrl, 'fingerprint' => $liveFingerprint])

    {{-- Galerie-Wechsel (identisch zur Shop-Detailseite) --}}
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
