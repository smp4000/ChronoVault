{{--
=============================================================================
Shop: Bestätigungsseite VOR der Kunden-Entscheidung zum Gegenangebot
=============================================================================
Sicherheitskonzept (Audit 2026-07-22): Der signierte Mail-Link (GET) zeigt
nur diese Seite. Erst der POST-Klick auf den Bestätigungs-Button löst die
verbindliche Entscheidung aus — Mail-Scanner/Prefetch können so nichts
ungewollt annehmen. Das Formular postet auf dieselbe signierte URL
(Signatur bleibt in der Query erhalten), CSRF ist nicht nötig, da die
signierte URL selbst das Autorisierungs-Token ist — der Standard-Token
wird trotzdem mitgesendet (web-Middleware).

Erwartet: $proposal (PriceProposal mit watch.brand), $decision (annehmen|ablehnen).
=============================================================================
--}}
@extends('shop.layout')

@section('title', $decision === 'annehmen' ? 'Angebot verbindlich annehmen' : 'Angebot ablehnen')

@section('content')
    <div class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center sm:px-6">
        @php
            $accepting = $decision === 'annehmen';
            $watchName = $proposal->watch?->fullName() ?? 'Ihre Wunschuhr';
            $total = $proposal->counterTotal() ?? (float) $proposal->proposed_price;
            $shipping = (float) ($proposal->shipping_price ?? 0);
        @endphp

        <div class="flex h-16 w-16 items-center justify-center rounded-full {{ $accepting ? 'bg-blue-100' : 'bg-neutral-100' }}">
            <svg class="h-8 w-8 {{ $accepting ? 'text-blue-800' : 'text-neutral-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
        </div>

        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">
            {{ $accepting ? 'Angebot verbindlich annehmen?' : 'Angebot ablehnen?' }}
        </h1>

        <p class="mt-4 max-w-lg text-base leading-relaxed text-neutral-600">
            @if ($accepting)
                Mit Ihrer Bestätigung kommt der Kauf von
                <span class="font-semibold text-neutral-900">{{ $watchName }}</span>
                zum Gesamtpreis von
                <span class="font-semibold text-neutral-900">{{ number_format($total, 2, ',', '.') }} €</span>
                @if ($shipping > 0)
                    (inkl. {{ number_format($shipping, 2, ',', '.') }} € Versand)
                @endif
                <span class="font-semibold">verbindlich</span> zustande. Sie erhalten anschließend
                Rechnung, Kaufvertrag und die Zahlungsinformationen per E-Mail.
            @else
                Sie lehnen das Angebot für
                <span class="font-semibold text-neutral-900">{{ $watchName }}</span>
                ab. Der Vorgang wird geschlossen — Sie können uns jederzeit
                einen neuen Vorschlag senden.
            @endif
        </p>

        <form method="POST" action="{{ url()->full() }}" class="mt-8">
            @csrf
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-full px-8 py-3 text-sm font-semibold text-white transition {{ $accepting ? 'bg-blue-800 hover:bg-blue-700' : 'bg-neutral-700 hover:bg-neutral-600' }}">
                {{ $accepting ? 'Ja, verbindlich kaufen' : 'Ja, Angebot ablehnen' }}
            </button>
        </form>

        <a href="{{ route('shop.index') }}" class="mt-6 text-sm text-neutral-500 transition hover:text-neutral-700">
            Abbrechen und zur Kollektion
        </a>
    </div>
@endsection
