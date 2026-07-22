{{--
=============================================================================
Marktplatz: Rechtsseite der zentralen Plattform (Impressum/Datenschutz)
=============================================================================
Inhalte kommen aus resources/legal/*.txt (Betreiber-gepflegt). Ausgabe
bewusst escaped ({{ }}) mit pre-line — kein HTML aus Textdateien rendern.
Erwartet: $title, $content (string|null).
=============================================================================
--}}
@extends('marketplace.layout')

@section('title', $title)

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Rechtliches</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900">{{ $title }}</h1>

        @if ($content !== null)
            <div class="mt-8 whitespace-pre-line text-sm leading-relaxed text-neutral-700">{{ $content }}</div>
        @else
            <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                Diese Seite ist noch nicht befüllt. Der Plattform-Betreiber hinterlegt den
                Text in <code>resources/legal/</code> (siehe docs/SECURITY.md).
            </div>
        @endif
    </div>
@endsection
