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

    {{-- Schlanker, klebriger Header: Händlername links, Navigation rechts --}}
    <header class="sticky top-0 z-40 border-b border-neutral-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('shop.index') }}" class="group flex items-center gap-3">
                <span class="block h-2.5 w-2.5 rounded-full bg-blue-800 transition group-hover:bg-blue-600"></span>
                <span class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-900">
                    {{ tenant('name') }}
                </span>
            </a>

            <nav class="flex items-center gap-6 text-sm">
                <a href="{{ route('shop.index') }}"
                   class="font-medium text-neutral-600 transition hover:text-blue-800">
                    Kollektion
                </a>
                <a href="{{ route('shop.auctions.index') }}"
                   class="font-medium text-neutral-600 transition hover:text-blue-800">
                    Auktionen
                </a>
                <a href="#kontakt"
                   class="font-medium text-neutral-600 transition hover:text-blue-800">
                    Kontakt
                </a>
            </nav>
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
                <p class="flex flex-wrap gap-x-4 gap-y-1">
                    <a href="{{ route('shop.legal.imprint') }}" class="transition hover:text-blue-800">Impressum</a>
                    <a href="{{ route('shop.legal.privacy') }}" class="transition hover:text-blue-800">Datenschutz</a>
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
