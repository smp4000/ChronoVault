<?php

/**
 * =========================================================================
 * marketplace:sync — Zentralen Marktplatz komplett neu aufbauen
 * =========================================================================
 *
 * Zweck:
 *   Backfill/Reparatur des Listings-Spiegels: Läuft über ALLE Mandanten,
 *   spiegelt jede veröffentlichte kaufbare Uhr in die zentrale
 *   marketplace_listings-Tabelle und räumt verwaiste Zeilen weg
 *   (Sicherheitsnetz zum Observer — z. B. nach Foto-Änderungen, die
 *   kein Watch-saved auslösen, oder nach Importen).
 *
 * Nutzung:
 *   php artisan marketplace:sync           — alle Mandanten
 *   Zeitplan: täglich 00:30 (routes/console.php), nach der
 *   nächtlichen Wertermittlung.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Marketplace\SyncWatchToMarketplaceAction;
use App\Models\MarketplaceListing;
use App\Models\Tenant;
use App\Models\Watch;
use Illuminate\Console\Command;

class SyncMarketplaceCommand extends Command
{
    protected $signature = 'marketplace:sync';

    protected $description = 'Spiegelt alle veröffentlichten, kaufbaren Uhren aller Mandanten in den zentralen Marktplatz';

    public function handle(SyncWatchToMarketplaceAction $action): int
    {
        $total = 0;

        foreach (Tenant::query()->cursor() as $tenant) {
            $tenant->run(function () use ($action, $tenant, &$total): void {
                $kept = [];

                // Alle Uhren durch den Sync schicken — die Action entscheidet
                // selbst, ob gelistet oder entfernt wird.
                foreach (Watch::query()->with(['brand', 'media'])->cursor() as $watch) {
                    $action->execute($watch);

                    if ((bool) $watch->getAttribute('is_published') && $watch->isBuyableInShop()) {
                        $kept[] = (string) $watch->getKey();
                        $total++;
                    }
                }

                // Verwaiste Zeilen (z. B. hart gelöschte Uhren) aufräumen
                $action->pruneTenant((string) $tenant->getTenantKey(), $kept);
            });

            $this->line('Mandant '.$tenant->getTenantKey().' synchronisiert.');
        }

        // Angebote gelöschter Mandanten entfernen (Tenant weg → Listing weg)
        MarketplaceListing::query()
            ->whereNotIn('tenant_id', Tenant::query()->select('id'))
            ->delete();

        $this->info($total.' Angebot(e) auf dem Marktplatz.');

        return self::SUCCESS;
    }
}
