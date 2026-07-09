{{--
=============================================================================
Shop: Bestätigungsseite nach Kunden-Entscheidung zum Gegenangebot
=============================================================================
Erwartet: $success (bool), $heading, $text.
=============================================================================
--}}
@extends('shop.layout')

@section('title', $heading)

@section('content')
    <div class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center sm:px-6">
        <div class="flex h-16 w-16 items-center justify-center rounded-full {{ $success ? 'bg-green-100' : 'bg-neutral-100' }}">
            @if ($success)
                <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            @else
                <svg class="h-8 w-8 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
            @endif
        </div>

        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">{{ $heading }}</h1>
        <p class="mt-4 max-w-lg text-base leading-relaxed text-neutral-600">{{ $text }}</p>

        <a href="{{ route('shop.index') }}"
           class="mt-8 inline-flex items-center justify-center rounded-full bg-blue-800 px-8 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
            Zur Kollektion
        </a>
    </div>
@endsection
