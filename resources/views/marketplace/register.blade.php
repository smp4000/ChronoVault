{{--
=============================================================================
Marktplatz: „Jetzt verkaufen" — Selbst-Registrierung (privat/gewerblich)
=============================================================================
Erwartet: $capA, $capB (Rechenfrage). Legt über den
SellerRegistrationController einen kompletten Verkäufer an (Tenant mit
eigener Datenbank, Subdomain und Inhaber-Zugang).
=============================================================================
--}}
@extends('marketplace.layout')

@section('title', 'Jetzt verkaufen')

@section('content')
    <div class="mx-auto max-w-3xl px-4 pt-14 pb-10 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">Jetzt verkaufen</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
            Ihre eigene Verkaufsseite — in 2 Minuten
        </h1>
        <p class="mt-4 text-base leading-relaxed text-neutral-500">
            Ob private Sammlung oder gewerblicher Handel: Sie erhalten Ihre eigene
            Seite unter <span class="font-medium text-neutral-700">ihr-name.{{ config('chronovault.tenant_domain_suffix') }}</span>
            mit Foto-Verwaltung, KI-Unterstützung und Sofortkauf — und jedes
            veröffentlichte Angebot erscheint automatisch hier auf dem Marktplatz.
        </p>

        @if (session('registration_error') || isset($registration_error))
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
                {{ session('registration_error') ?? $registration_error }}
            </div>
        @endif

        <form method="POST" action="{{ url('/verkaufen') }}" class="mt-10 space-y-8">
            @csrf
            <input type="hidden" name="captcha_a" value="{{ $capA }}">
            <input type="hidden" name="captcha_b" value="{{ $capB }}">

            {{-- Verkäufer-Typ (eBay-Prinzip) --}}
            <div>
                <p class="text-sm font-semibold text-neutral-900">Wie möchten Sie verkaufen? *</p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-neutral-300 p-4 transition has-[:checked]:border-blue-800 has-[:checked]:bg-blue-50/50">
                        <input type="radio" name="seller_type" value="private" @checked(old('seller_type') === 'private')
                               class="mt-1 text-blue-800 focus:ring-blue-800">
                        <span>
                            <span class="block font-medium text-neutral-900">Privat</span>
                            <span class="mt-1 block text-xs leading-relaxed text-neutral-500">
                                Sie verkaufen Uhren aus Ihrer privaten Sammlung —
                                gelegentlich, ohne Gewerbe.
                            </span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-neutral-300 p-4 transition has-[:checked]:border-blue-800 has-[:checked]:bg-blue-50/50">
                        <input type="radio" name="seller_type" value="commercial" @checked(old('seller_type', 'commercial') === 'commercial')
                               class="mt-1 text-blue-800 focus:ring-blue-800">
                        <span>
                            <span class="block font-medium text-neutral-900">Gewerblich</span>
                            <span class="mt-1 block text-xs leading-relaxed text-neutral-500">
                                Sie handeln als Unternehmen — mit Impressumspflicht,
                                Widerrufsrecht und Rechnungen.
                            </span>
                        </span>
                    </label>
                </div>
                @error('seller_type') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Verkaufsseite --}}
            <div class="space-y-4">
                <p class="text-sm font-semibold text-neutral-900">Ihre Verkaufsseite</p>
                <div>
                    <label for="shop_name" class="block text-xs font-medium text-neutral-600">Name der Seite *</label>
                    <input type="text" id="shop_name" name="shop_name" required value="{{ old('shop_name') }}"
                           placeholder="z. B. Müllers Uhren oder Sammlung C. Weber"
                           class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                    @error('shop_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="slug" class="block text-xs font-medium text-neutral-600">Wunsch-Adresse (optional)</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="text" id="slug" name="slug" value="{{ old('slug') }}"
                               placeholder="mueller-uhren"
                               class="w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        <span class="shrink-0 text-sm text-neutral-500">.{{ config('chronovault.tenant_domain_suffix') }}</span>
                    </div>
                    <p class="mt-1 text-xs text-neutral-400">Nur Kleinbuchstaben, Zahlen und Bindestriche. Leer lassen = wird aus dem Namen erzeugt.</p>
                    @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Zugang --}}
            <div class="space-y-4">
                <p class="text-sm font-semibold text-neutral-900">Ihr Zugang</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="owner_name" class="block text-xs font-medium text-neutral-600">Ihr Name *</label>
                        <input type="text" id="owner_name" name="owner_name" required value="{{ old('owner_name') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('owner_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="block text-xs font-medium text-neutral-600">E-Mail-Adresse *</label>
                        <input type="email" id="email" name="email" required value="{{ old('email') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="block text-xs font-medium text-neutral-600">Passwort * (mind. 10 Zeichen)</label>
                        <input type="password" id="password" name="password" required
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-xs font-medium text-neutral-600">Passwort wiederholen *</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                    </div>
                </div>
            </div>

            {{-- Spam-Schutz + DSGVO --}}
            <div class="space-y-4">
                <div class="sm:max-w-xs">
                    <label for="captcha" class="block text-xs font-medium text-neutral-600">Sicherheitsfrage: {{ $capA }} + {{ $capB }} = ? *</label>
                    <input type="number" id="captcha" name="captcha" required
                           class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                    @error('captcha') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-3 text-xs leading-relaxed text-neutral-600">
                    <input type="checkbox" name="privacy" value="1" required class="mt-0.5 rounded border-neutral-300 text-blue-800 focus:ring-blue-800">
                    <span>
                        Ich stimme zu, dass meine Angaben zur Einrichtung und zum Betrieb
                        meiner Verkaufsseite verarbeitet werden.*
                    </span>
                </label>
                @error('privacy') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="inline-flex items-center justify-center rounded-full bg-blue-800 px-8 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                Verkaufsseite kostenlos erstellen
            </button>

            <p class="text-xs leading-relaxed text-neutral-400">
                Hinweis für gewerbliche Verkäufer: Impressum, Datenschutzerklärung und
                Widerrufsbelehrung pflegen Sie nach der Registrierung bequem in den
                Betriebsdaten — auf Wunsch mit KI-Unterstützung.
            </p>
        </form>
    </div>
@endsection
