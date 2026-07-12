{{--
=============================================================================
Marktplatz-Layout — Zentrale Plattform-Seite (chrono-save.de)
=============================================================================
Gleiche Design-Sprache wie die Händler-Shops (weiß, Blau als Akzent),
aber Plattform-Branding statt Händlername. eBay-Prinzip: Angebote
privater UND gewerblicher Verkäufer; jede Kachel führt in den Shop
des Verkäufers. Tailwind only, responsiv.
=============================================================================
--}}
<!DOCTYPE html>
<html lang="de" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Marktplatz') — {{ config('app.name', 'ChronoVault') }}</title>
    <meta name="description" content="@yield('meta_description', 'Der Uhren-Marktplatz: geprüfte Angebote privater und gewerblicher Verkäufer — Kauf direkt beim Verkäufer.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-neutral-900 antialiased">

    <header class="sticky top-0 z-40 border-b border-neutral-200 bg-white/95 backdrop-blur">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center gap-4">
                <a href="{{ url('/') }}" class="group flex shrink-0 items-center gap-3">
                    <span class="block h-2.5 w-2.5 rounded-full bg-blue-800 transition group-hover:bg-blue-600"></span>
                    <span class="text-sm font-semibold uppercase tracking-[0.2em] text-neutral-900">
                        {{ config('app.name', 'ChronoVault') }}
                    </span>
                    <span class="hidden rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-800 sm:block">
                        Marktplatz
                    </span>
                </a>

                {{-- Marktplatz-Suche (Desktop) --}}
                <form method="GET" action="{{ url('/') }}"
                      class="relative hidden max-w-md flex-1 md:block">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input type="search" name="suche" value="{{ $search ?? '' }}"
                           placeholder="Marke, Modell oder Referenz suchen"
                           class="w-full rounded-full border border-neutral-300 bg-neutral-50 py-2 pl-10 pr-4 text-sm text-neutral-800 placeholder:text-neutral-400 focus:border-blue-800 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-800">
                </form>

                <nav class="ml-auto flex shrink-0 items-center gap-5 text-sm md:ml-0">
                    <a href="{{ url('/') }}"
                       class="font-medium text-neutral-600 transition hover:text-blue-800">
                        Alle Angebote
                    </a>
                    <a href="/app" class="hidden font-medium text-neutral-600 transition hover:text-blue-800 sm:block">
                        Verkäufer-Login
                    </a>
                </nav>
            </div>

            {{-- Marktplatz-Suche (Mobile) --}}
            <form method="GET" action="{{ url('/') }}" class="relative pb-3 md:hidden">
                <svg class="pointer-events-none absolute left-3.5 top-[38%] h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" name="suche" value="{{ $search ?? '' }}"
                       placeholder="Marke, Modell oder Referenz suchen"
                       class="w-full rounded-full border border-neutral-300 bg-neutral-50 py-2 pl-10 pr-4 text-sm text-neutral-800 placeholder:text-neutral-400 focus:border-blue-800 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-800">
            </form>
        </div>

        {{-- Trust-Leiste der Plattform --}}
        <div class="border-t border-neutral-100 bg-neutral-50/80">
            <div class="mx-auto flex max-w-7xl items-center gap-x-6 gap-y-1 overflow-x-auto px-4 py-2 text-xs text-neutral-500 sm:px-6 lg:px-8">
                @foreach ([
                    ['m4.5 12.75 6 6 9-13.5', 'Private & gewerbliche Verkäufer'],
                    ['M12 1.5 3 5.25v6c0 5.02 3.84 8.53 9 9.75 5.16-1.22 9-4.73 9-9.75v-6L12 1.5Z', 'Kauf direkt beim Verkäufer'],
                    ['M3 8.25 12 3l9 5.25M4.5 9.75v9a.75.75 0 0 0 .75.75h13.5a.75.75 0 0 0 .75-.75v-9', 'Dokumentierte Uhren mit Fotos'],
                ] as [$path, $label])
                    <span class="flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                        <svg class="h-3.5 w-3.5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                        </svg>
                        {{ $label }}
                    </span>
                @endforeach
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="mt-24 border-t border-neutral-200 bg-neutral-50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-900">
                        {{ config('app.name', 'ChronoVault') }}
                    </p>
                    <p class="mt-3 max-w-md text-sm leading-relaxed text-neutral-500">
                        Der Marktplatz für Uhren — Angebote privater und gewerblicher
                        Verkäufer. Kauf, Anfrage und Preisvorschlag laufen direkt und
                        sicher im Shop des jeweiligen Verkäufers.
                    </p>
                </div>
                <div class="text-sm text-neutral-500">
                    <p class="font-medium text-neutral-700">Selbst verkaufen?</p>
                    <p class="mt-2 max-w-xs">
                        Ob privat oder gewerblich — bald können Sie sich hier
                        registrieren und Ihre Uhren mit eigener Shop-Seite anbieten.
                    </p>
                </div>
            </div>
            <div class="mt-10 flex flex-col gap-2 border-t border-neutral-200 pt-6 text-xs text-neutral-400 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} {{ config('app.name', 'ChronoVault') }} · Preise und Abwicklung gemäß Angaben des jeweiligen Verkäufers.</p>
                <p>
                    <a href="/app" class="transition hover:text-blue-800">Verkäufer-Login</a>
                </p>
            </div>
        </div>
    </footer>

</body>
</html>
