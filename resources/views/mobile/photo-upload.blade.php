{{--
=============================================================================
Mobile Foto-Aufnahme (QR-Code aus dem Uhren-Formular) — Modul 4
=============================================================================
Schlanke Handy-Seite mit Platzhalter-Kacheln je PhotoSlot: Tippen öffnet
die Kamera, das Foto wird sofort hochgeladen (fetch auf den signierten
Store-Link) und ersetzt das vorhandene Slot-Foto. Tipps-Dialog nach
Chrono24-Vorbild. Erwartet: $watch, $slots (value/label/photoUrl), $storeUrl.
=============================================================================
--}}
<!DOCTYPE html>
<html lang="de" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Fotos aufnehmen — {{ $watch->fullName() }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <header class="border-b border-neutral-200 bg-white px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-800">{{ tenant('name') }}</p>
        <h1 class="mt-1 text-lg font-semibold leading-tight">Fotos aufnehmen</h1>
        <p class="text-sm text-neutral-500">{{ $watch->fullName() }}</p>
    </header>

    <main class="mx-auto max-w-lg px-4 py-5">

        <button type="button" onclick="document.getElementById('cv-tips').classList.remove('hidden')"
                class="mb-5 inline-flex items-center gap-2 text-sm font-medium text-blue-800 underline decoration-blue-300 underline-offset-4">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
            Tipps für gelungene Bilder
        </button>

        {{-- Platzhalter-Kacheln je Slot --}}
        <div class="grid grid-cols-2 gap-4">
            @foreach ($slots as $slot)
                <label class="block cursor-pointer" data-slot-card="{{ $slot['value'] }}">
                    <div class="relative aspect-square overflow-hidden rounded-2xl border-2 border-dashed border-neutral-300 bg-white transition
                                {{ $slot['photoUrl'] ? 'border-solid border-green-300' : '' }}">
                        @if ($slot['photoUrl'])
                            <img src="{{ $slot['photoUrl'] }}" alt="{{ $slot['label'] }}" class="h-full w-full object-cover" data-slot-img>
                        @else
                            <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-neutral-400" data-slot-empty>
                                <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" /></svg>
                                <span class="text-xs">Foto aufnehmen</span>
                            </div>
                        @endif

                        {{-- Upload-Spinner --}}
                        <div class="absolute inset-0 hidden items-center justify-center bg-white/70" data-slot-spinner>
                            <svg class="h-8 w-8 animate-spin text-blue-800" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path></svg>
                        </div>

                        {{-- Erfolgs-Haken --}}
                        <div class="absolute right-2 top-2 {{ $slot['photoUrl'] ? '' : 'hidden' }} rounded-full bg-green-500 p-1 text-white" data-slot-check>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-center text-sm font-medium text-neutral-700">{{ $slot['label'] }}</p>
                    <input type="file" accept="image/*" capture="environment" class="hidden" data-slot-input="{{ $slot['value'] }}">
                </label>
            @endforeach
        </div>

        <p class="mt-6 text-center text-xs leading-relaxed text-neutral-400">
            Jedes Foto wird sofort gespeichert und ersetzt das vorhandene Bild des
            Platzhalters. Danach einfach diese Seite schließen — die Fotos sind
            bereits an der Uhr.
        </p>

        <p data-upload-error class="mt-3 hidden rounded-xl bg-red-50 px-4 py-3 text-center text-sm text-red-800"></p>
    </main>

    {{-- Tipps-Dialog --}}
    <div id="cv-tips" class="fixed inset-0 z-50 hidden items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
         onclick="this.classList.add('hidden')">
        <div class="w-full max-w-md rounded-t-3xl bg-white p-6 sm:rounded-3xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Tipps für gelungene Bilder</h2>
                <button type="button" onclick="document.getElementById('cv-tips').classList.add('hidden')"
                        class="text-2xl leading-none text-neutral-400" aria-label="Schließen">&times;</button>
            </div>
            <ul class="mt-4 space-y-3 text-sm leading-relaxed text-neutral-700">
                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-800"></span>Machen Sie die Bilder bei Tageslicht und in heller Umgebung.</li>
                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-800"></span>Fotografieren Sie die Uhr aus allen wichtigen Blickwinkeln — die Platzhalter führen Sie durch.</li>
                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-800"></span>Stellen Sie etwaige Mängel wie Kratzer oder Dellen transparent dar.</li>
                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-800"></span>Neutraler, ruhiger Hintergrund — die Uhr soll im Mittelpunkt stehen.</li>
            </ul>
            <button type="button" onclick="document.getElementById('cv-tips').classList.add('hidden')"
                    class="mt-6 w-full rounded-xl bg-blue-900 px-4 py-3 text-sm font-semibold text-white">
                Alles klar
            </button>
        </div>
    </div>

    <script>
        (function () {
            var storeUrl = @json($storeUrl);
            var token = document.querySelector('meta[name="csrf-token"]').content;
            var errorBox = document.querySelector('[data-upload-error]');

            document.querySelectorAll('[data-slot-input]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (!input.files || !input.files[0]) { return; }

                    var slot = input.dataset.slotInput;
                    var card = document.querySelector('[data-slot-card="' + slot + '"]');
                    var spinner = card.querySelector('[data-slot-spinner]');

                    errorBox.classList.add('hidden');
                    spinner.classList.remove('hidden');
                    spinner.classList.add('flex');

                    var form = new FormData();
                    form.append('photo', input.files[0]);
                    form.append('slot', slot);

                    fetch(storeUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: form,
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                return response.json().then(function (data) {
                                    throw new Error(data.message || 'Upload fehlgeschlagen — bitte erneut versuchen.');
                                });
                            }

                            return response.json();
                        })
                        .then(function (data) {
                            // Platzhalter durch das frische Foto ersetzen
                            var empty = card.querySelector('[data-slot-empty]');
                            var img = card.querySelector('[data-slot-img]');

                            if (!img) {
                                img = document.createElement('img');
                                img.setAttribute('data-slot-img', '');
                                img.className = 'h-full w-full object-cover';
                                spinner.parentNode.insertBefore(img, spinner);
                            }

                            img.src = data.url + (data.url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();

                            if (empty) { empty.remove(); }

                            var frame = card.querySelector('div');
                            frame.classList.remove('border-dashed', 'border-neutral-300');
                            frame.classList.add('border-solid', 'border-green-300');
                            card.querySelector('[data-slot-check]').classList.remove('hidden');
                        })
                        .catch(function (error) {
                            errorBox.textContent = error.message;
                            errorBox.classList.remove('hidden');
                        })
                        .finally(function () {
                            spinner.classList.add('hidden');
                            spinner.classList.remove('flex');
                            input.value = '';
                        });
                });
            });
        })();
    </script>
</body>
</html>
