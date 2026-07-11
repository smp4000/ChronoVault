{{--
=============================================================================
Mobile Foto-Aufnahme (QR-Code aus dem Uhren-Formular) — Modul 4
=============================================================================
Ablauf: Fotos werden auf dem Handy erst GESAMMELT (Platzhalter-Slots +
beliebig viele „Weitere Fotos"), dann mit „Übertragen" gemeinsam
hochgeladen. Erst beim Übertragen werden Slot-Fotos serverseitig
ersetzt. Nach Erfolg: Abschluss-Ansicht mit Schließen-Hinweis.
Erwartet: $watch, $slots (value/label/photoUrl), $storeUrl.
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
<body class="min-h-screen bg-neutral-50 pb-28 font-sans text-neutral-900">

    <header class="border-b border-neutral-200 bg-white px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-800">{{ tenant('name') }}</p>
        <h1 class="mt-1 text-lg font-semibold leading-tight">Fotos aufnehmen</h1>
        <p class="text-sm text-neutral-500">{{ $watch->fullName() }}</p>
    </header>

    <main id="cv-main" class="mx-auto max-w-lg px-4 py-5">

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
                                {{ $slot['photoUrl'] ? 'border-solid border-green-300' : '' }}" data-slot-frame>
                        @if ($slot['photoUrl'])
                            <img src="{{ $slot['photoUrl'] }}" alt="{{ $slot['label'] }}" class="h-full w-full object-cover" data-slot-img>
                        @else
                            <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-neutral-400" data-slot-empty>
                                <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" /></svg>
                                <span class="text-xs">Foto aufnehmen</span>
                            </div>
                        @endif

                        {{-- Badge: neu ausgewählt, noch nicht übertragen --}}
                        <div class="absolute left-2 top-2 hidden rounded-full bg-blue-800 px-2 py-0.5 text-[10px] font-semibold text-white" data-slot-pending>
                            Neu — noch nicht übertragen
                        </div>

                        {{-- Haken: bereits auf dem Server --}}
                        <div class="absolute right-2 top-2 {{ $slot['photoUrl'] ? '' : 'hidden' }} rounded-full bg-green-500 p-1 text-white" data-slot-check>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-center text-sm font-medium text-neutral-700">{{ $slot['label'] }}</p>
                    <input type="file" accept="image/*" capture="environment" class="hidden" data-slot-input="{{ $slot['value'] }}">
                </label>
            @endforeach
        </div>

        {{-- Weitere Fotos: beliebig viele, ohne Slot --}}
        <section class="mt-8">
            <h2 class="text-sm font-semibold text-neutral-700">Weitere Fotos</h2>
            <p class="mt-1 text-xs text-neutral-500">Beliebig viele zusätzliche Aufnahmen — Details, Mängel, Besonderheiten.</p>

            <div id="cv-extra-grid" class="mt-3 grid grid-cols-3 gap-3">
                <label class="block cursor-pointer" id="cv-extra-add">
                    <div class="flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-neutral-300 bg-white text-neutral-400">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        <span class="text-[11px]">Hinzufügen</span>
                    </div>
                    <input type="file" accept="image/*" multiple class="hidden" id="cv-extra-input">
                </label>
            </div>
        </section>

        <p data-upload-error class="mt-4 hidden rounded-xl bg-red-50 px-4 py-3 text-center text-sm text-red-800"></p>
    </main>

    {{-- Abschluss-Ansicht (nach erfolgreichem Übertragen) --}}
    <div id="cv-done" class="mx-auto hidden max-w-lg px-4 py-16 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
            <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
        </div>
        <h2 class="mt-5 text-xl font-semibold">Alles übertragen!</h2>
        <p id="cv-done-text" class="mt-2 text-sm leading-relaxed text-neutral-600"></p>
        <p class="mt-4 text-sm text-neutral-500">
            Sie können diese Seite jetzt schließen — die Fotos sind an der Uhr
            gespeichert. Am PC einfach die Uhren-Seite neu laden.
        </p>
        <button type="button" onclick="window.close()"
                class="mt-6 rounded-full bg-blue-800 px-8 py-3 text-sm font-semibold text-white">
            Seite schließen
        </button>
    </div>

    {{-- Fixe Übertragen-Leiste --}}
    <div id="cv-transfer-bar" class="fixed inset-x-0 bottom-0 border-t border-neutral-200 bg-white/95 px-4 py-3 backdrop-blur">
        <div class="mx-auto max-w-lg">
            <button type="button" id="cv-transfer" disabled
                    class="w-full rounded-full bg-blue-800 px-6 py-3.5 text-sm font-semibold text-white transition disabled:bg-neutral-300">
                Übertragen
            </button>
        </div>
    </div>

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
            var transferBtn = document.getElementById('cv-transfer');

            // Lokal gesammelte Fotos — hochgeladen wird erst bei "Übertragen"
            var slotFiles = {};
            var extraFiles = [];

            var pendingCount = function () {
                return Object.keys(slotFiles).length + extraFiles.length;
            };

            var refreshTransferButton = function () {
                var count = pendingCount();
                transferBtn.disabled = count === 0;
                transferBtn.textContent = count === 0
                    ? 'Übertragen'
                    : count + ' ' + (count === 1 ? 'Foto' : 'Fotos') + ' übertragen';
            };

            // Slot-Auswahl: nur Vorschau, kein Upload
            document.querySelectorAll('[data-slot-input]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (!input.files || !input.files[0]) { return; }

                    var slot = input.dataset.slotInput;
                    var card = document.querySelector('[data-slot-card="' + slot + '"]');

                    slotFiles[slot] = input.files[0];

                    var empty = card.querySelector('[data-slot-empty]');
                    var img = card.querySelector('[data-slot-img]');

                    if (!img) {
                        img = document.createElement('img');
                        img.setAttribute('data-slot-img', '');
                        img.className = 'h-full w-full object-cover';
                        card.querySelector('[data-slot-frame]').prepend(img);
                    }

                    img.src = URL.createObjectURL(input.files[0]);

                    if (empty) { empty.remove(); }

                    card.querySelector('[data-slot-pending]').classList.remove('hidden');
                    card.querySelector('[data-slot-check]').classList.add('hidden');

                    var frame = card.querySelector('[data-slot-frame]');
                    frame.classList.remove('border-dashed', 'border-neutral-300', 'border-green-300');
                    frame.classList.add('border-solid', 'border-blue-400');

                    input.value = '';
                    refreshTransferButton();
                });
            });

            // Weitere Fotos: mehrere auf einmal, mit Entfernen-Knopf
            var extraGrid = document.getElementById('cv-extra-grid');
            var extraAdd = document.getElementById('cv-extra-add');

            document.getElementById('cv-extra-input').addEventListener('change', function () {
                Array.prototype.forEach.call(this.files || [], function (file) {
                    extraFiles.push(file);

                    var tile = document.createElement('div');
                    tile.className = 'relative aspect-square overflow-hidden rounded-2xl border-2 border-solid border-blue-400 bg-white';
                    tile.innerHTML = '<img class="h-full w-full object-cover">'
                        + '<button type="button" class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-sm text-white" aria-label="Entfernen">&times;</button>';
                    tile.querySelector('img').src = URL.createObjectURL(file);
                    tile.querySelector('button').addEventListener('click', function () {
                        extraFiles.splice(extraFiles.indexOf(file), 1);
                        tile.remove();
                        refreshTransferButton();
                    });

                    extraGrid.insertBefore(tile, extraAdd);
                });

                this.value = '';
                refreshTransferButton();
            });

            var upload = function (file, slot) {
                var form = new FormData();
                form.append('photo', file);
                form.append('slot', slot);

                return fetch(storeUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: form,
                }).then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (data) {
                            throw new Error(data.message || 'Upload fehlgeschlagen.');
                        });
                    }
                });
            };

            // Übertragen: alle gesammelten Fotos nacheinander hochladen
            transferBtn.addEventListener('click', function () {
                var jobs = [];

                Object.keys(slotFiles).forEach(function (slot) {
                    jobs.push({ file: slotFiles[slot], slot: slot });
                });
                extraFiles.forEach(function (file) {
                    jobs.push({ file: file, slot: 'extra' });
                });

                if (jobs.length === 0) { return; }

                transferBtn.disabled = true;
                errorBox.classList.add('hidden');

                var index = 0;
                var total = jobs.length;

                var next = function () {
                    if (index >= total) {
                        // Fertig: Abschluss-Ansicht zeigen
                        document.getElementById('cv-main').classList.add('hidden');
                        document.getElementById('cv-transfer-bar').classList.add('hidden');
                        document.getElementById('cv-done-text').textContent =
                            total + ' ' + (total === 1 ? 'Foto wurde' : 'Fotos wurden') + ' erfolgreich übertragen.';
                        document.getElementById('cv-done').classList.remove('hidden');

                        return;
                    }

                    transferBtn.textContent = 'Übertrage ' + (index + 1) + ' von ' + total + ' …';

                    upload(jobs[index].file, jobs[index].slot)
                        .then(function () {
                            if (jobs[index].slot !== 'extra') {
                                delete slotFiles[jobs[index].slot];
                            } else {
                                extraFiles.splice(extraFiles.indexOf(jobs[index].file), 1);
                            }

                            index++;
                            next();
                        })
                        .catch(function (error) {
                            errorBox.textContent = error.message + ' Bereits übertragene Fotos sind gespeichert — bitte erneut auf Übertragen tippen.';
                            errorBox.classList.remove('hidden');
                            refreshTransferButton();
                        });
                };

                next();
            });

            refreshTransferButton();
        })();
    </script>
</body>
</html>
