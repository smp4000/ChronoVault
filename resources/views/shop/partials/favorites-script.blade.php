{{--
=============================================================================
Shop-Partial: Favoriten (Merkliste) — localStorage, ohne Kundenkonto
=============================================================================
Event-Delegation auf .cv-fav-Buttons (Kachel + Detailseite): Klick
toggelt die Uhr-ID in localStorage("cvFavorites") und färbt das Herz.
Zusätzlich: "Nur Favoriten"-Filter (Button .cv-fav-filter) blendet im
Grid alle Kacheln (.cv-card) aus, die nicht gemerkt sind.
=============================================================================
--}}
@once
    <script>
        (function () {
            var KEY = 'cvFavorites';

            var load = function () {
                try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; }
            };
            var save = function (ids) { localStorage.setItem(KEY, JSON.stringify(ids)); };

            var paint = function () {
                var ids = load();

                document.querySelectorAll('.cv-fav').forEach(function (btn) {
                    var active = ids.indexOf(btn.dataset.watch) !== -1;
                    var icon = btn.querySelector('.cv-fav-icon');

                    btn.classList.toggle('text-red-500', active);
                    btn.classList.toggle('text-neutral-400', !active);

                    if (icon) {
                        icon.setAttribute('fill', active ? 'currentColor' : 'none');
                    }
                });

                var counter = document.querySelector('.cv-fav-count');

                if (counter) {
                    counter.textContent = String(ids.length);
                }
            };

            var applyFilter = function () {
                var filterBtn = document.querySelector('.cv-fav-filter');

                if (!filterBtn) { return; }

                var onlyFavs = filterBtn.dataset.active === '1';
                var ids = load();

                document.querySelectorAll('.cv-card').forEach(function (card) {
                    card.style.display = (!onlyFavs || ids.indexOf(card.dataset.watch) !== -1) ? '' : 'none';
                });
            };

            document.addEventListener('click', function (event) {
                var favBtn = event.target.closest('.cv-fav');

                if (favBtn) {
                    // Klick aufs Herz darf den Kachel-Link nicht auslösen
                    event.preventDefault();
                    event.stopPropagation();

                    var ids = load();
                    var idx = ids.indexOf(favBtn.dataset.watch);

                    if (idx === -1) { ids.push(favBtn.dataset.watch); } else { ids.splice(idx, 1); }

                    save(ids);
                    paint();
                    applyFilter();

                    return;
                }

                var filterBtn = event.target.closest('.cv-fav-filter');

                if (filterBtn) {
                    filterBtn.dataset.active = filterBtn.dataset.active === '1' ? '0' : '1';
                    filterBtn.classList.toggle('border-blue-800', filterBtn.dataset.active === '1');
                    filterBtn.classList.toggle('bg-blue-800', filterBtn.dataset.active === '1');
                    filterBtn.classList.toggle('text-white', filterBtn.dataset.active === '1');
                    applyFilter();
                }
            });

            paint();
        })();
    </script>
@endonce
