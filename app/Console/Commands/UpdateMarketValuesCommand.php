<?php

/**
 * =========================================================================
 * UpdateMarketValuesCommand — Nächtliche automatische Wertermittlung
 * =========================================================================
 *
 * Zweck:
 *   Recherchiert per Perplexity den aktuellen Marktwert aller
 *   UNVERKAUFTEN Uhren mit Referenznummer und schreibt das Ergebnis
 *   als Bewertung (RecordValuationAction → Historie + Schnellzugriff
 *   current_market_value/last_valuation_at, Quelle „KI-Marktrecherche").
 *
 * Läuft im TENANT-Kontext — Scheduler: `tenants:run
 *   watches:update-market-values` täglich um 00:00 (routes/console.php).
 *
 * Kosten-/Robustheits-Leitplanken:
 *   - Ohne PERPLEXITY_API_KEY: sauberer Hinweis, kein Fehler.
 *   - Uhren mit Bewertung der letzten 20 Stunden werden übersprungen
 *     (kein Doppel-Lauf bei manuellem Nachstart; --force übersteuert).
 *   - --limit begrenzt die Anzahl pro Lauf (API-Kosten).
 *   - Fehler einer Uhr stoppen NIE den Rest (report + weiter).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Valuations\RecordValuationAction;
use App\Enums\ValuationSource;
use App\Enums\WatchStatus;
use App\Models\Watch;
use App\Services\MarketValueLookupService;
use Illuminate\Console\Command;
use Throwable;

class UpdateMarketValuesCommand extends Command
{
    protected $signature = 'watches:update-market-values
        {--limit=0 : Maximale Anzahl Uhren pro Lauf (0 = alle)}
        {--force : Auch Uhren mit frischer Bewertung erneut recherchieren}';

    protected $description = 'Recherchiert den aktuellen Marktwert unverkaufter Uhren per KI und erfasst Bewertungen (Tenant-Kontext)';

    public function handle(MarketValueLookupService $lookup, RecordValuationAction $record): int
    {
        $apiKey = config('services.perplexity.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->warn('PERPLEXITY_API_KEY nicht konfiguriert — Wertermittlung übersprungen.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        $watches = Watch::query()
            ->whereNot('status', WatchStatus::Sold->value)
            ->whereNotNull('reference_number')
            ->when(! $this->option('force'), fn ($query) => $query->where(
                fn ($query) => $query
                    ->whereNull('last_valuation_at')
                    ->orWhere('last_valuation_at', '<', now()->subHours(20)),
            ))
            ->with('brand')
            ->orderBy('last_valuation_at')
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->get();

        if ($watches->isEmpty()) {
            $this->info('Keine Uhren zur Wertermittlung fällig.');

            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;

        foreach ($watches as $watch) {
            try {
                $data = $lookup->lookup($watch);

                $record->execute($watch, [
                    'source' => ValuationSource::AiResearch,
                    'market_value' => $data->marketValue,
                    'value_low' => $data->valueLow,
                    'value_high' => $data->valueHigh,
                    'valued_at' => now(),
                    'summary' => $data->summary,
                    'source_urls' => $data->sourceUrls,
                    'notes' => 'Automatische nächtliche Wertermittlung.',
                ]);

                $this->info($watch->fullName().' → '.number_format($data->marketValue, 0, ',', '.').' €');
                $updated++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn($watch->fullName().' → fehlgeschlagen: '.$exception->getMessage());
                $failed++;
            }
        }

        $this->info($updated.' Bewertung(en) erfasst, '.$failed.' fehlgeschlagen.');

        return self::SUCCESS;
    }
}
