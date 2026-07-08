<?php

/**
 * =========================================================================
 * StartDueAuctionsCommand — Fällige Auktionen automatisch starten
 * =========================================================================
 *
 * Zweck:
 *   Setzt geplante Auktionen, deren Startzeit erreicht ist, auf "Läuft".
 *   Läuft im TENANT-Kontext — Aufruf über den Scheduler mit
 *   `tenants:run auctions:start-due` (jede Minute, routes/console.php).
 *
 * WARUM zusätzlich zum Controller-Fallback:
 *   Der öffentliche Katalog startet fällige Auktionen ebenfalls beim
 *   Seitenaufruf — der Scheduler garantiert den pünktlichen Start aber
 *   auch OHNE Besucher (z. B. für Panel-Anzeige und Bietfenster-Logik).
 *
 * Nutzung:
 *   php artisan tenants:run auctions:start-due        (alle Mandanten)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use Illuminate\Console\Command;

class StartDueAuctionsCommand extends Command
{
    protected $signature = 'auctions:start-due';

    protected $description = 'Startet geplante Auktionen, deren Startzeit erreicht ist (Tenant-Kontext)';

    public function handle(): int
    {
        $started = Auction::query()
            ->where('status', AuctionStatus::Scheduled->value)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->update(['status' => AuctionStatus::Live->value]);

        $this->info($started.' Auktion(en) gestartet.');

        return self::SUCCESS;
    }
}
