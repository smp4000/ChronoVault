{{--
=============================================================================
Shop-Layout — Öffentliches Schaufenster des Händlers (Tenant-Domain)
=============================================================================
Design-Referenz: docs/DESIGN.md — grimmeissen.de-Stil, Blau als Akzent.
Weiße Basis, viel Weißraum, schlanker Header, dezente Typografie.
Tailwind only (Utility-Klassen, kein Bootstrap), responsiv.
=============================================================================
--}}
<!DOCTYPE html>
<html lang="de" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kollektion') — {{ tenant('name') }}</title>
    <meta name="description" content="@yield('meta_description', 'Ausgewählte Zeitmesser von ' . tenant('name') . ' — geprüfte Qualität, faire Preise.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-neutral-900 antialiased">

    {{-- Klebriger Header (Chrono24-Stil): Marke links, prominente Suche
         mittig, Navigation rechts; auf Mobile rutscht die Suche in eine
         zweite Zeile. --}}
    <header class="sticky top-0 z-40 border-b border-neutral-200 bg-white/95 backdrop-blur">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center gap-4">
                <a href="{{ route('shop.index') }}" class="group flex shrink-0 items-center gap-3">
                    <span class="block h-2.5 w-2.5 rounded-full bg-blue-800 transition group-hover:bg-blue-600"></span>
                    <span class="text-sm font-semibold uppercase tracking-[0.2em] text-neutral-900">
                        {{ tenant('name') }}
                    </span>
                </a>

                {{-- Suchleiste (Desktop): Freitext über Marke, Modell, Referenz --}}
                <form method="GET" action="{{ route('shop.index') }}"
                      class="relative hidden max-w-md flex-1 md:block">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input type="search" name="suche" value="{{ $search ?? '' }}"
                           placeholder="Marke, Modell oder Referenz suchen"
                           class="w-full rounded-full border border-neutral-300 bg-neutral-50 py-2 pl-10 pr-4 text-sm text-neutral-800 placeholder:text-neutral-400 focus:border-blue-800 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-800">
                </form>

                <nav class="ml-auto flex shrink-0 items-center gap-5 text-sm md:ml-0">
                    <a href="{{ route('shop.index') }}"
                       class="hidden font-medium text-neutral-600 transition hover:text-blue-800 sm:block">
                        Kollektion
                    </a>
                    <a href="{{ route('shop.auctions.index') }}"
                       class="font-medium text-neutral-600 transition hover:text-blue-800">
                        Auktionen
                    </a>
                    <a href="#kontakt"
                       class="hidden font-medium text-neutral-600 transition hover:text-blue-800 sm:block">
                        Kontakt
                    </a>
                </nav>
            </div>

            {{-- Suchleiste (Mobile): eigene Zeile unter dem Header-Balken --}}
            <form method="GET" action="{{ route('shop.index') }}" class="relative pb-3 md:hidden">
                <svg class="pointer-events-none absolute left-3.5 top-[38%] h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" name="suche" value="{{ $search ?? '' }}"
                       placeholder="Marke, Modell oder Referenz suchen"
                       class="w-full rounded-full border border-neutral-300 bg-neutral-50 py-2 pl-10 pr-4 text-sm text-neutral-800 placeholder:text-neutral-400 focus:border-blue-800 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-800">
            </form>
        </div>

        {{-- Trust-Leiste: Vertrauenssignale wie bei großen Marktplätzen --}}
        <div class="border-t border-neutral-100 bg-neutral-50/80">
            <div class="mx-auto flex max-w-7xl items-center gap-x-6 gap-y-1 overflow-x-auto px-4 py-2 text-xs text-neutral-500 sm:px-6 lg:px-8">
                @foreach ([
                    ['m4.5 12.75 6 6 9-13.5', 'Geprüft & dokumentiert'],
                    ['M12 1.5 3 5.25v6c0 5.02 3.84 8.53 9 9.75 5.16-1.22 9-4.73 9-9.75v-6L12 1.5Z', 'Sichere Zahlung per Überweisung'],
                    ['M3 8.25 12 3l9 5.25M4.5 9.75v9a.75.75 0 0 0 .75.75h13.5a.75.75 0 0 0 .75-.75v-9', '14 Tage Widerrufsrecht'],
                    ['M8 10h.01M12 10h.01M16 10h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'Persönliche Beratung'],
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

    {{-- Footer mit Kontakt-Anker und dezentem Händler-Login --}}
    <footer id="kontakt" class="mt-24 border-t border-neutral-200 bg-neutral-50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-900">
                        {{ tenant('name') }}
                    </p>
                    <p class="mt-3 max-w-md text-sm leading-relaxed text-neutral-500">
                        Ausgewählte Zeitmesser — geprüft, dokumentiert und mit Sorgfalt
                        aufbereitet. Sprechen Sie uns gerne zu jeder Uhr aus unserer
                        Kollektion an.
                    </p>
                </div>
                <div class="text-sm text-neutral-500">
                    <p class="font-medium text-neutral-700">Anfragen</p>
                    <p class="mt-2">
                        Bitte geben Sie bei Anfragen die Referenznummer der Uhr an.
                    </p>
                </div>
            </div>
            <div class="mt-10 flex flex-col gap-2 border-t border-neutral-200 pt-6 text-xs text-neutral-400 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} {{ tenant('name') }} · Alle Preise inkl. MwSt., zzgl. Versand.</p>
                {{-- Pflicht-Links (Impressumspflicht/DSGVO/Fernabsatz) --}}
                <p>
                    <a href="{{ route('shop.legal.imprint') }}" class="transition hover:text-blue-800">Impressum</a>
                    &nbsp;·&nbsp;
                    <a href="{{ route('shop.legal.privacy') }}" class="transition hover:text-blue-800">Datenschutz</a>
                    &nbsp;·&nbsp;
                    <a href="{{ route('shop.legal.revocation') }}" class="transition hover:text-blue-800">Widerruf</a>
                </p>
                <p>
                    Bereitgestellt über <span class="font-medium text-blue-800">ChronoVault</span>
                    · <a href="/app" class="transition hover:text-blue-800">Händler-Login</a>
                </p>
            </div>
        </div>
    </footer>

</body>
</html>
