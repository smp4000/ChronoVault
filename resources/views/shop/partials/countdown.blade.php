{{--
=============================================================================
Shop-Partial: Live-Countdown bis zum Auktionsende (Modul 8b)
=============================================================================
Erwartet: $endsAt (Carbon). Tickt sekündlich per Vanilla-JS:
- zeigt daneben das konkrete Auktionsende (Datum + Uhrzeit)
- die letzten 60 Sekunden: ROT, fett und pulsierend (Endspurt)
- bei 0 lädt die Seite einmalig neu (Server rendert das geschlossene
  Bietfenster). @once verhindert doppelte Skripte bei mehrfachem Include.
=============================================================================
--}}
<div class="flex flex-wrap items-center gap-3">
    <span class="cv-countdown inline-flex items-center gap-2 rounded-full bg-blue-800 px-4 py-1.5 text-sm font-semibold tabular-nums text-white transition-colors"
          data-ends="{{ $endsAt->getTimestamp() * 1000 }}">
        <svg class="h-4 w-4 opacity-75" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <span class="cv-countdown-label">Endet in …</span>
    </span>
    <span class="text-xs text-neutral-500">
        Auktionsende: <span class="font-medium text-neutral-700">{{ $endsAt->format('d.m.Y \u\m H:i') }} Uhr</span>
    </span>
</div>

@once
    <script>
        (function () {
            var pad = function (n) { return String(n).padStart(2, '0'); };

            var tick = function () {
                document.querySelectorAll('.cv-countdown').forEach(function (el) {
                    var diff = Math.floor((parseInt(el.dataset.ends, 10) - Date.now()) / 1000);
                    var label = el.querySelector('.cv-countdown-label');

                    if (diff <= 0) {
                        label.textContent = 'Beendet';

                        // Einmalig neu laden: der Server rendert dann das
                        // geschlossene Bietfenster.
                        if (!window.__cvCountdownReloaded) {
                            window.__cvCountdownReloaded = true;
                            setTimeout(function () { window.location.reload(); }, 1500);
                        }

                        return;
                    }

                    // Endspurt: letzte 60 Sekunden rot, fett und pulsierend
                    if (diff <= 60 && !el.classList.contains('cv-urgent')) {
                        el.classList.add('cv-urgent', 'bg-red-600', 'font-bold', 'animate-pulse');
                        el.classList.remove('bg-blue-800', 'font-semibold');
                    }

                    var d = Math.floor(diff / 86400);
                    var h = Math.floor((diff % 86400) / 3600);
                    var m = Math.floor((diff % 3600) / 60);
                    var s = diff % 60;

                    label.textContent = 'Endet in '
                        + (d > 0 ? d + (d === 1 ? ' Tag ' : ' Tage ') : '')
                        + pad(h) + ':' + pad(m) + ':' + pad(s);
                });
            };

            tick();
            setInterval(tick, 1000);
        })();
    </script>
@endonce
