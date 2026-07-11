<?php

/**
 * =========================================================================
 * UpdateWishlistValuesCommand — Nächtliche Wunschlisten-Beobachtung
 * =========================================================================
 *
 * Zweck:
 *   Bewertet alle AKTIVEN Wunschlisten-Einträge per KI-Marktrecherche
 *   (ValuateWishlistItemAction — inkl. Zielpreis-Alarm-Mail).
 *
 * Läuft im TENANT-Kontext — Scheduler: `tenants:run
 *   wishlist:update-values` täglich um 00:30 (routes/console.php),
 *   bewusst NACH der Bestands-Wertermittlung (00:00).
 *
 * Leitplanken wie watches:update-market-values: ohne API-Key sauberer
 * Hinweis; 20-h-Sperre gegen Doppel-Läufe (--force übersteuert);
 * --limit begrenzt API-Kosten; Fehler stoppen nie den Rest.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Wishlist\ValuateWishlistItemAction;
use App\Enums\WishlistStatus;
use App\Models\WishlistItem;
use Illuminate\Console\Command;
use Throwable;

class UpdateWishlistValuesCommand extends Command
{
    protected $signature = 'wishlist:update-values
        {--limit=0 : Maximale Anzahl Einträge pro Lauf (0 = alle)}
        {--force : Auch frisch bewertete Einträge erneut recherchieren}';

    protected $description = 'Bewertet aktive Wunschlisten-Einträge per KI und verschickt Zielpreis-Alarme (Tenant-Kontext)';

    public function handle(ValuateWishlistItemAction $valuate): int
    {
        $apiKey = config('services.perplexity.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->warn('PERPLEXITY_API_KEY nicht konfiguriert — Wunschlisten-Bewertung übersprungen.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        $items = WishlistItem::query()
            ->where('status', WishlistStatus::Active->value)
            ->when(! $this->option('force'), fn ($query) => $query->where(
                fn ($query) => $query
                    ->whereNull('last_valuation_at')
                    ->orWhere('last_valuation_at', '<', now()->subHours(20)),
            ))
            ->with('brand')
            ->orderBy('last_valuation_at')
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->get();

        if ($items->isEmpty()) {
            $this->info('Keine Wunschlisten-Einträge zur Bewertung fällig.');

            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $item = $valuate->execute($item);

                $this->info($item->displayName().' → '.number_format((float) $item->current_market_value, 0, ',', '.').' €'
                    .($item->isTargetReached() ? ' (Zielpreis erreicht!)' : ''));
                $updated++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn($item->displayName().' → fehlgeschlagen: '.$exception->getMessage());
                $failed++;
            }
        }

        $this->info($updated.' Bewertung(en) erfasst, '.$failed.' fehlgeschlagen.');

        return self::SUCCESS;
    }
}
