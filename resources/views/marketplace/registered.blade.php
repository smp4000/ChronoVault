{{--
=============================================================================
Marktplatz: Registrierung erfolgreich — Zugänge und nächste Schritte
=============================================================================
Erwartet: $shopName, $sellerType, $shopUrl, $panelUrl, $email.
=============================================================================
--}}
@extends('marketplace.layout')

@section('title', 'Willkommen')

@section('content')
    <div class="mx-auto max-w-2xl px-4 pt-16 pb-10 text-center sm:px-6 lg:px-8">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
            <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </div>

        <h1 class="mt-6 text-3xl font-semibold tracking-tight text-neutral-900">
            Willkommen, {{ $shopName }}!
        </h1>
        <p class="mt-3 text-base leading-relaxed text-neutral-500">
            Ihre Verkaufsseite ist fertig eingerichtet
            ({{ $sellerType === 'private' ? 'privater Verkäufer' : 'gewerblicher Verkäufer' }}).
        </p>

        <div class="mt-8 space-y-3 text-left">
            <a href="{{ $panelUrl }}"
               class="block rounded-2xl border border-blue-200 bg-blue-50/60 px-5 py-4 transition hover:border-blue-400">
                <p class="text-sm font-semibold text-blue-900">1. Einloggen &amp; erste Uhr einstellen</p>
                <p class="mt-1 break-all text-sm text-blue-800">{{ $panelUrl }}</p>
                <p class="mt-1 text-xs text-neutral-500">Anmeldung mit {{ $email }} und Ihrem Passwort.</p>
            </a>
            <div class="rounded-2xl border border-neutral-200 px-5 py-4">
                <p class="text-sm font-semibold text-neutral-900">2. Ihre öffentliche Verkaufsseite</p>
                <p class="mt-1 break-all text-sm text-blue-800">{{ $shopUrl }}</p>
                <p class="mt-1 text-xs text-neutral-500">Jede veröffentlichte Uhr erscheint dort — und automatisch hier auf dem Marktplatz.</p>
            </div>
            @if ($sellerType === 'commercial')
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 px-5 py-4">
                    <p class="text-sm font-semibold text-amber-900">3. Rechtliches nicht vergessen</p>
                    <p class="mt-1 text-xs leading-relaxed text-neutral-600">
                        Als gewerblicher Verkäufer pflegen Sie Impressum, Datenschutzerklärung
                        und Widerrufsbelehrung im Panel unter „Betriebsdaten" — auf Wunsch
                        erstellt die KI dort einen Entwurf.
                    </p>
                </div>
            @endif
        </div>

        <a href="{{ url('/') }}" class="mt-8 inline-block text-sm font-medium text-blue-800 transition hover:text-blue-600">
            &larr; Zurück zum Marktplatz
        </a>
    </div>
@endsection
