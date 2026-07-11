{{--
=============================================================================
Shop: Rechtsseite (Impressum / Datenschutz / Widerruf)
=============================================================================
Rendert den in den Betriebsdaten gepflegten Text (Zeilenumbrüche bleiben
erhalten). Ohne Inhalt: deutlicher Hinweis — besser als eine leere Seite.
Erwartet: $title, $content (string|null).
=============================================================================
--}}
@extends('shop.layout')

@section('title', $title)

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">Rechtliches</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-900">{{ $title }}</h1>
        <div class="mt-4 h-px w-16 bg-blue-800"></div>

        @if ($content !== null)
            <div class="mt-8 whitespace-pre-line text-sm leading-relaxed text-neutral-700">{{ $content }}</div>
        @else
            <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                Für diese Seite wurde noch kein Inhalt hinterlegt. Als Betreiber pflegen
                Sie den Text im Händler-Panel unter <strong>Betriebsdaten → Rechtliches</strong>.
            </div>
        @endif
    </div>
@endsection
