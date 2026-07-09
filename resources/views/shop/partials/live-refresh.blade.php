{{--
=============================================================================
Shop-Partial: Live-Aktualisierung der Auktionsseiten (Modul 8b)
=============================================================================
Pollt alle 10 Sekunden den Status-Endpunkt und lädt die Seite neu, sobald
sich serverseitig etwas geändert hat (Auktionsstart, Auktionsende, neues
Gebot, Zuschlag). Der Endpunkt stößt dabei auch Start/Abwicklung an —
"Push"-Gefühl ohne Websockets, robust auf jedem Hosting.

Kein Reload, solange der Besucher gerade in einem Formularfeld tippt
(sonst verlöre er seine Gebotseingabe) — nachgeholt beim nächsten Tick.

Erwartet: $statusUrl, $fingerprint.
=============================================================================
--}}
<div class="cv-live" data-url="{{ $statusUrl }}" data-fingerprint="{{ $fingerprint }}" hidden></div>

@once
    <script>
        (function () {
            var el = document.querySelector('.cv-live');

            if (!el || !window.fetch) {
                return;
            }

            var typing = function () {
                var active = document.activeElement;

                return active && (active.tagName === 'INPUT'
                    || active.tagName === 'TEXTAREA'
                    || active.tagName === 'SELECT');
            };

            var check = function () {
                fetch(el.dataset.url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.fingerprint && data.fingerprint !== el.dataset.fingerprint && !typing()) {
                            window.location.reload();
                        }
                    })
                    .catch(function () { /* Netzfehler ignorieren — nächster Versuch folgt */ });
            };

            setInterval(check, 10000);

            // Tab wieder im Vordergrund → sofort prüfen statt warten
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    check();
                }
            });
        })();
    </script>
@endonce
