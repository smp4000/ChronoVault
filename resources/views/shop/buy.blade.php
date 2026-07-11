{{--
=============================================================================
Kaufseite — verbindlicher Sofortkauf zum Festpreis (Shop)
=============================================================================
Erwartet: $watch (publishedInShop, mit asking_price).
Button-Beschriftung „zahlungspflichtig kaufen" — Button-Lösung (§ 312j BGB).
=============================================================================
--}}
@extends('shop.layout')

@section('title', 'Kaufen — '.$watch->fullName())

@php
    $formatEur = fn ($value): string => number_format((float) $value, 2, ',', '.').' €';
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 pt-12 sm:px-6 lg:px-8 lg:pt-16">

        <a href="{{ route('shop.show', $watch) }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 transition hover:text-blue-800">
            &larr; Zurück zur Uhr
        </a>

        <h1 class="mt-6 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
            Verbindlich kaufen
        </h1>

        {{-- Zusammenfassung --}}
        <div class="mt-8 flex items-center gap-5 rounded-2xl border border-neutral-200 p-5">
            @if ($watch->firstPhotoUrl())
                <img src="{{ $watch->firstPhotoUrl() }}" alt="{{ $watch->fullName() }}"
                     class="h-24 w-24 rounded-xl border border-neutral-200 object-cover">
            @endif
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-blue-800">{{ $watch->brand->name }}</p>
                <p class="mt-0.5 truncate font-medium text-neutral-900">{{ $watch->model_name }}</p>
                @if ($watch->reference_number)
                    <p class="text-xs text-neutral-500">Referenz {{ $watch->reference_number }}</p>
                @endif
                <p class="mt-1 text-lg font-semibold text-blue-900">{{ $formatEur($watch->asking_price) }}</p>
                <p class="text-xs text-neutral-400">inkl. MwSt., zzgl. Versand · Zahlung per Überweisung</p>
            </div>
        </div>

        @if (session('purchase_success'))
            <div class="mt-8 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-900">
                {{ session('purchase_success') }}
            </div>
        @else
            @error('purchase')
                <div class="mt-8 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
                    {{ $message }}
                </div>
            @enderror

            <form method="POST" action="{{ route('shop.purchase', $watch) }}" class="mt-8 rounded-3xl border border-neutral-200 p-6 sm:p-8">
                @csrf

                <p class="font-medium text-neutral-900">Ihre Liefer- und Rechnungsdaten</p>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="block text-xs font-medium text-neutral-600">Vorname</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs font-medium text-neutral-600">Nachname *</label>
                        <input type="text" id="last_name" name="last_name" required value="{{ old('last_name') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="block text-xs font-medium text-neutral-600">E-Mail *</label>
                        <input type="email" id="email" name="email" required value="{{ old('email') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-medium text-neutral-600">Telefon</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="street" class="block text-xs font-medium text-neutral-600">Straße und Hausnummer *</label>
                        <input type="text" id="street" name="street" required value="{{ old('street') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('street') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="postal_code" class="block text-xs font-medium text-neutral-600">PLZ *</label>
                        <input type="text" id="postal_code" name="postal_code" required value="{{ old('postal_code') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('postal_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="city" class="block text-xs font-medium text-neutral-600">Ort *</label>
                        <input type="text" id="city" name="city" required value="{{ old('city') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="country" class="block text-xs font-medium text-neutral-600">Land *</label>
                        <input type="text" id="country" name="country" required value="{{ old('country', 'Deutschland') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('country') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="mt-6 flex items-start gap-3 text-sm text-neutral-700">
                    <input type="checkbox" name="accept_binding" value="1" required
                           class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-blue-800 focus:ring-blue-800">
                    <span>
                        Ich kaufe die Uhr <strong>verbindlich</strong> zum Preis von
                        <strong>{{ $formatEur($watch->asking_price) }}</strong> (inkl. MwSt., zzgl. Versand)
                        und zahle per Überweisung innerhalb von 7 Tagen.
                    </span>
                </label>
                @error('accept_binding') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                <button type="submit"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-blue-800 px-8 py-3.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
                    Jetzt zahlungspflichtig kaufen — {{ $formatEur($watch->asking_price) }}
                </button>
                <p class="mt-3 text-xs text-neutral-400">
                    Nach dem Kauf erhalten Sie die Kaufbestätigung mit allen
                    Zahlungsinformationen per E-Mail. Der Versand erfolgt nach Zahlungseingang.
                </p>
                <p class="mt-2 text-xs leading-relaxed text-neutral-400">
                    Es gelten unsere
                    <a href="{{ route('shop.legal.revocation') }}" class="underline hover:text-blue-800">Widerrufsbelehrung</a>
                    und
                    <a href="{{ route('shop.legal.privacy') }}" class="underline hover:text-blue-800">Datenschutzerklärung</a>.
                </p>
            </form>
        @endif
    </div>
@endsection
