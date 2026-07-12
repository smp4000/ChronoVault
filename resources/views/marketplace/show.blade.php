{{--
=============================================================================
Marktplatz: Zentrale Angebotsseite (Sammelstelle, eBay-Prinzip)
=============================================================================
Vor allem für PRIVATE Verkäufer ohne eigenen Shop: Galerie, Kenndaten,
Privatverkaufs-Hinweis, Anfrage-Formular und Preisvorschlag-Dialog —
alles direkt auf der Plattform. Erwartet: $listing, $capA, $capB.
=============================================================================
--}}
@extends('marketplace.layout')

@section('title', $listing->brand_name.' '.$listing->model_name)

@php
    $photos = (array) ($listing->photo_urls ?? []);
@endphp

@section('content')
    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8 lg:pt-12">

        <a href="{{ url('/') }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 transition hover:text-blue-800">
            &larr; Zurück zum Marktplatz
        </a>

        <div class="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">

            {{-- Galerie --}}
            <div>
                <div class="aspect-square overflow-hidden rounded-3xl border border-neutral-200 bg-neutral-50">
                    @if ($photos !== [])
                        <img id="mp-main-photo" src="{{ $photos[0] }}" alt="{{ $listing->brand_name }} {{ $listing->model_name }}"
                             class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <svg class="h-16 w-16 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    @endif
                </div>

                @if (count($photos) > 1)
                    <div class="mt-4 grid grid-cols-5 gap-3">
                        @foreach ($photos as $photo)
                            <button type="button" data-photo="{{ $photo }}"
                                    class="mp-thumb aspect-square overflow-hidden rounded-xl border transition {{ $loop->first ? 'border-blue-800 ring-1 ring-blue-800' : 'border-neutral-200 hover:border-blue-300' }}">
                                <img src="{{ $photo }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Daten, Preis, Aktionen --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-800">{{ $listing->brand_name }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                    {{ $listing->model_name }}
                </h1>
                @if ($listing->reference_number)
                    <p class="mt-2 text-sm text-neutral-500">Referenz {{ $listing->reference_number }}</p>
                @endif

                {{-- Verkäufer-Zeile --}}
                <p class="mt-3 flex items-center gap-2 text-sm text-neutral-600">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold">
                        <span class="h-1.5 w-1.5 rounded-full {{ $listing->isPrivateSale() ? 'bg-amber-500' : 'bg-blue-700' }}"></span>
                        {{ $listing->sellerTypeLabel() }}
                    </span>
                    Angeboten von {{ $listing->seller_name }}
                </p>

                <div class="mt-6">
                    @if ($listing->formattedPrice())
                        <p class="text-3xl font-semibold {{ $listing->discount_percent !== null ? 'text-red-600' : 'text-blue-900' }}">
                            {{ $listing->formattedPrice() }}
                            @if ($listing->discount_percent !== null && $listing->formattedPreviousPrice())
                                <span class="ml-2 align-middle text-xl text-neutral-400 line-through decoration-1">{{ $listing->formattedPreviousPrice() }}</span>
                            @endif
                        </p>
                        @if ($listing->isPrivateSale())
                            <p class="mt-1 text-xs text-neutral-400">Privatverkauf — keine MwSt. ausweisbar, zzgl. Versand nach Absprache</p>
                        @endif
                    @else
                        <p class="text-2xl font-medium text-neutral-700">Preis auf Anfrage</p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        @if ($listing->direct_buy && $listing->price !== null && ! $listing->isPrivateSale())
                            {{-- Gewerblich: Sofortkauf im Shop des Händlers --}}
                            <a href="{{ $listing->shop_url }}/uhren/{{ $listing->watch_id }}/kaufen"
                               class="inline-flex items-center justify-center rounded-full bg-blue-800 px-8 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                                Jetzt verbindlich kaufen
                            </a>
                        @endif
                        <a href="#anfrage"
                           class="inline-flex items-center justify-center rounded-full border border-blue-800 px-6 py-3 text-sm font-semibold text-blue-800 transition hover:bg-blue-50">
                            Frage stellen
                        </a>
                        @if ($listing->price !== null)
                            <button type="button" onclick="mpOpenModal('mp-propose-modal')"
                                    class="inline-flex items-center justify-center rounded-full border border-neutral-300 px-6 py-3 text-sm font-semibold text-neutral-700 transition hover:border-blue-800 hover:text-blue-800">
                                Preis vorschlagen
                            </button>
                        @endif
                    </div>

                    @if ($listing->isPrivateSale() && $listing->direct_buy && $listing->price !== null)
                        <p class="mt-3 text-xs text-neutral-500">
                            Sofortkauf für dieses Privatangebot folgt in Kürze — bis dahin
                            einfach anfragen, der Verkäufer meldet sich umgehend.
                        </p>
                    @endif
                </div>

                {{-- Ausstattungs-Chips --}}
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach (array_filter([
                        $listing->condition_label,
                        $listing->has_box ? 'Mit Box' : null,
                        $listing->has_papers ? 'Mit Papieren' : null,
                        $listing->year_label,
                        $listing->material_label,
                        $listing->diameter_label,
                    ]) as $chip)
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700">{{ $chip }}</span>
                    @endforeach
                </div>

                {{-- Privatverkaufs-Hinweis (Rechtslage) --}}
                @if ($listing->isPrivateSale())
                    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/60 px-5 py-4 text-xs leading-relaxed text-neutral-700">
                        <strong class="text-amber-900">Privatverkauf:</strong>
                        Dieses Angebot stammt von einer Privatperson. Es besteht keine
                        Gewährleistung, keine Garantie und kein Widerrufsrecht; die
                        Abwicklung erfolgt direkt zwischen Käufer und Verkäufer.
                    </div>
                @endif

                {{-- Erfolgs-/Fehlermeldungen --}}
                @if (session('inquiry_success'))
                    <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-900">
                        {{ session('inquiry_success') }}
                    </div>
                @endif
                @if (session('proposal_success'))
                    <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-900">
                        {{ session('proposal_success') }}
                    </div>
                @endif

                {{-- Anfrage-Formular --}}
                @unless (session('inquiry_success'))
                    <div id="anfrage" class="mt-8 rounded-2xl border border-blue-100 bg-blue-50/50 p-6">
                        <p class="font-medium text-neutral-900">Interesse an dieser Uhr?</p>
                        <p class="mt-1 text-sm leading-relaxed text-neutral-600">
                            Ihre Anfrage geht direkt an {{ $listing->seller_name }}.
                        </p>

                        <form method="POST" action="{{ url('/angebot/'.$listing->getKey().'/anfrage') }}" class="mt-4">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="inquiry_name" class="block text-xs font-medium text-neutral-600">Name *</label>
                                    <input type="text" id="inquiry_name" name="name" required value="{{ old('name') }}"
                                           class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="inquiry_email" class="block text-xs font-medium text-neutral-600">E-Mail *</label>
                                    <input type="email" id="inquiry_email" name="email" required value="{{ old('email') }}"
                                           class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="inquiry_message" class="block text-xs font-medium text-neutral-600">Ihre Nachricht *</label>
                                    <textarea id="inquiry_message" name="message" rows="3" required
                                              class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">{{ old('message', 'Ich interessiere mich für die '.$listing->brand_name.' '.$listing->model_name.'. Bitte kontaktieren Sie mich.') }}</textarea>
                                    @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <button type="submit"
                                    class="mt-4 inline-flex items-center justify-center rounded-full bg-blue-800 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">
                                Anfrage senden
                            </button>
                        </form>
                    </div>
                @endunless

                {{-- Beschreibung --}}
                @if (filled($listing->description))
                    <div class="mt-10">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Beschreibung</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-neutral-700">{{ $listing->description }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Preisvorschlag-Dialog --}}
    @if ($listing->price !== null)
        <div id="mp-propose-modal" class="fixed inset-0 z-50 {{ $errors->hasAny(['proposed_price', 'captcha', 'privacy']) ? 'flex' : 'hidden' }} items-start justify-center overflow-y-auto bg-black/40 p-4 pt-16">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl" onclick="event.stopPropagation()">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-neutral-900">Wunschpreis angeben &amp; absenden</h2>
                    <button type="button" onclick="mpCloseModal('mp-propose-modal')"
                            class="text-2xl leading-none text-neutral-400 transition hover:text-neutral-700" aria-label="Schließen">&times;</button>
                </div>

                <form method="POST" action="{{ url('/angebot/'.$listing->getKey().'/preisvorschlag') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="captcha_a" value="{{ $capA }}">
                    <input type="hidden" name="captcha_b" value="{{ $capB }}">

                    <div>
                        <label for="propose_price" class="block text-sm font-medium text-neutral-700">Preisvorschlag *</label>
                        <input type="number" id="propose_price" name="proposed_price" required min="1" step="1"
                               value="{{ old('proposed_price') }}" placeholder="Ihr Preisvorschlag in €"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('proposed_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_name" class="block text-sm font-medium text-neutral-700">Name *</label>
                        <input type="text" id="propose_name" name="name" required value="{{ old('name') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_email" class="block text-sm font-medium text-neutral-700">E-Mail-Adresse *</label>
                        <input type="email" id="propose_email" name="email" required value="{{ old('email') }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_captcha" class="block text-sm font-medium text-neutral-700">Sicherheitsfrage *</label>
                        <input type="number" id="propose_captcha" name="captcha" required
                               placeholder="Bitte rechnen Sie {{ $capA }} + {{ $capB }}"
                               class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">
                        @error('captcha') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="propose_message" class="block text-sm font-medium text-neutral-700">Nachricht</label>
                        <textarea id="propose_message" name="message" rows="3" placeholder="Nachricht schreiben"
                                  class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2.5 text-sm focus:border-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-800">{{ old('message') }}</textarea>
                    </div>
                    <label class="flex items-start gap-3 text-xs leading-relaxed text-neutral-600">
                        <input type="checkbox" name="privacy" value="1" required class="mt-0.5 rounded border-neutral-300 text-blue-800 focus:ring-blue-800">
                        <span>
                            Ich stimme zu, dass meine Angaben zur Beantwortung meiner Anfrage
                            erhoben und verarbeitet werden.*
                        </span>
                    </label>
                    @error('privacy') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <button type="submit"
                            class="w-full rounded-xl bg-blue-800 px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-blue-700">
                        Versenden
                    </button>
                </form>
            </div>
        </div>
    @endif

    <script>
        function mpOpenModal(id) {
            var modal = document.getElementById(id);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function mpCloseModal(id) {
            var modal = document.getElementById(id);
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        var proposeModal = document.getElementById('mp-propose-modal');
        if (proposeModal) {
            proposeModal.addEventListener('click', function () { mpCloseModal('mp-propose-modal'); });
        }

        document.querySelectorAll('.mp-thumb').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                document.getElementById('mp-main-photo').src = thumb.dataset.photo;
                document.querySelectorAll('.mp-thumb').forEach(function (other) {
                    other.classList.remove('border-blue-800', 'ring-1', 'ring-blue-800');
                    other.classList.add('border-neutral-200');
                });
                thumb.classList.add('border-blue-800', 'ring-1', 'ring-blue-800');
                thumb.classList.remove('border-neutral-200');
            });
        });
    </script>
@endsection
