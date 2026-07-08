<?php

/**
 * =========================================================================
 * FinalizeDueAuctionsCommand — Abgelaufene Auktionen abwickeln
 * =========================================================================
 *
 * Zweck:
 *   Rechnet Online-/Hybrid-Auktionen ab, deren Ende erreicht ist:
 *   Zuschlag an den Höchstbietenden (Limit-Prüfung) bzw. Rückgang —
 *   Logik in der FinalizeAuctionAction. Läuft im TENANT-Kontext:
 *   `php artisan tenants:run auctions:finalize-due` (Scheduler, jede
 *   Minute, routes/console.php).
 *
 * WARUM URL-Root gesetzt wird:
 *   Die Gewinner-Mail enthält einen signierten Link — im CLI-Kontext
 *   zeigt route() sonst auf die zentrale Domain statt auf die
 *   Tenant-Domain des Shops.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Auctions\FinalizeAuctionAction;
use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use App\Models\Auction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class FinalizeDueAuctionsCommand extends Command
{
    protected $signature = 'auctions:finalize-due';

    protected $description = 'Wickelt abgelaufene Online-Auktionen ab: Zuschlag bei erreichtem Limit, sonst Rückgang (Tenant-Kontext)';

    public function handle(FinalizeAuctionAction $finalize): int
    {
        $this->forceTenantUrlRoot();

        $due = Auction::query()
            ->where('status', AuctionStatus::Live->value)
            ->whereIn('venue', [AuctionVenue::Online->value, AuctionVenue::Hybrid->value])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($due as $auction) {
            $result = $finalize->execute($auction);

            $this->info(sprintf(
                '„%s": %d Zuschlag/Zuschläge, %d Rückgang/Rückgänge.',
                $auction->title,
                $result['sold'],
                $result['unsold'],
            ));
        }

        if ($due->isEmpty()) {
            $this->info('Keine abgelaufenen Auktionen.');
        }

        return self::SUCCESS;
    }

    /**
     * Signierte Links in Mails müssen auf die Tenant-Domain zeigen —
     * Schema/Port kommen aus APP_URL, der Host vom Mandanten.
     */
    private function forceTenantUrlRoot(): void
    {
        $domain = tenant()?->primaryDomain();

        if ($domain === null) {
            return;
        }

        $appUrl = parse_url((string) config('app.url'));

        URL::forceRootUrl(
            ($appUrl['scheme'] ?? 'http').'://'.$domain
            .(isset($appUrl['port']) ? ':'.$appUrl['port'] : '')
        );
    }
}
