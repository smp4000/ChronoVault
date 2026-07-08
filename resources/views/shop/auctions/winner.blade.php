{{--
=============================================================================
Gewinner-Datenseite — Liefer-/Rechnungsdaten nach Zuschlag (Modul 8b)
=============================================================================
Nur über den signierten Link aus der Zuschlag-Mail erreichbar.
Erwartet: $auction, $lot, $watch, $buyer (Contact).
WICHTIG: Das POST geht auf die volle signierte URL (fullUrl) — die
signed-Middleware validiert die Query-Signatur auch für POST.
=============================================================================
--}}
@extends('shop.layout')

@section('title', 'Ihre Daten — Los '.$lot->lot_number)

@php
    $formatEur = fn ($value): string => number_format((float) $value, 2, ',', '.').' €';
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 pt-12 sm:px-6 lg:px-8 lg:pt-16">

        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">Zuschlag — Los {{ $lot->lot_number }}</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
            Herzlichen Glückwunsch!
        </h1>
        <p class="mt-3 text-neutral-500">
            Der Zuschlag für
            <span class="font-medium text-neutral-900">{{ $watch->fullName() }}</span>
            ging an Sie — Hammerpreis
            <span class="font-semibold text-blue-900">{{ $formatEur($lot->hammer_price) }}</span>.
            Bitte vervollständigen Sie Ihre Daten für Rechnung und Versand.
        </p>

        @if (session('winner_success'))
            <div class="mt-8 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-900">
                {{ session('winner_success') }}
            </div>
        @else
            <form method="POST" action="{{ request()->fullUrl() }}" class="mt-8 rounded-3xl border border-neutral-200 p-6 sm:p-8">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="block text-xs font-medium text-neutral-600">Vorname</label>
                        <input type="text" id="first_name" name="first_name"
                               value="{{ old('first_name', $buyer->first_name) }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs font-medium text-neutral-600">Nachname *</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="{{ old('last_name', $buyer->last_name) }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="street" class="block text-xs font-medium text-neutral-600">Straße und Hausnummer *</label>
                        <input type="text" id="street" name="street" required
                               value="{{ old('street', $buyer->street) }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('street') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="postal_code" class="block text-xs font-medium text-neutral-600">PLZ *</label>
                        <input type="text" id="postal_code" name="postal_code" required
                               value="{{ old('postal_code', $buyer->postal_code) }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('postal_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="city" class="block text-xs font-medium text-neutral-600">Ort *</label>
                        <input type="text" id="city" name="city" required
                               value="{{ old('city', $buyer->city) }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="country" class="block text-xs font-medium text-neutral-600">Land *</label>
                        <input type="text" id="country" name="country" required
                               value="{{ old('country', $buyer->country ?? 'Deutschland') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('country') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-medium text-neutral-600">Telefon</label>
                        <input type="text" id="phone" name="phone"
                               value="{{ old('phone', $buyer->phone) }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                    </div>
                </div>

                <button type="submit"
                        class="mt-6 inline-flex items-center justify-center rounded-full bg-blue-800 px-8 py-3 text-sm font-medium text-white transition hover:bg-blue-700">
                    Daten absenden
                </button>
                <p class="mt-3 text-xs text-neutral-400">
                    Ihre Daten werden ausschließlich zur Abwicklung dieses Kaufs verwendet.
                </p>
            </form>
        @endif
    </div>
@endsection
