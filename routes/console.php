<?php

/**
 * =========================================================================
 * routes/console.php — Artisan-Closures und Scheduler-Definitionen
 * =========================================================================
 *
 * Scheduler (Produktion: Cron `* * * * * php artisan schedule:run`,
 * lokal alternativ `php artisan schedule:work`):
 *   - auctions:start-due je Mandant: geplante Auktionen pünktlich
 *     starten (Modul 8b). Der öffentliche Katalog hat zusätzlich einen
 *     Fallback beim Seitenaufruf — der Scheduler deckt den Fall ohne
 *     Besucher ab.
 * =========================================================================
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tenants:run', ['auctions:start-due'])
    ->everyMinute()
    ->withoutOverlapping();
