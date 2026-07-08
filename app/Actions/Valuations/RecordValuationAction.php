<?php

/**
 * =========================================================================
 * RecordValuationAction — Marktwert-Bewertung erfassen
 * =========================================================================
 *
 * Zweck:
 *   Legt den Bewertungs-Datensatz an (Historie) und spiegelt den Wert
 *   in die Schnellzugriffsfelder der Uhr (current_market_value,
 *   last_valuation_at) — aber nur, wenn die Bewertung nicht ÄLTER ist
 *   als die aktuellste vorhandene (nachgetragene Historie überschreibt
 *   den aktuellen Wert nicht).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Valuations;

use App\Enums\ValuationSource;
use App\Models\Valuation;
use App\Models\Watch;
use Illuminate\Support\Carbon;

class RecordValuationAction
{
    /**
     * @param  array{source: string|ValuationSource, market_value: float|string, value_low?: float|string|null, value_high?: float|string|null, valued_at?: string|\DateTimeInterface|null, summary?: string|null, source_urls?: array<int, string>|null, notes?: string|null}  $data
     */
    public function execute(Watch $watch, array $data): Valuation
    {
        $valuedAt = Carbon::parse($data['valued_at'] ?? now());

        $valuation = $watch->valuations()->create([
            'source' => $data['source'],
            'market_value' => $data['market_value'],
            'value_low' => $data['value_low'] ?? null,
            'value_high' => $data['value_high'] ?? null,
            'valued_at' => $valuedAt,
            'summary' => $data['summary'] ?? null,
            'source_urls' => $data['source_urls'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Schnellzugriff nur aktualisieren, wenn dies die neueste Bewertung ist.
        $lastValuationAt = $watch->getAttribute('last_valuation_at');

        if ($lastValuationAt === null || ! $valuedAt->lt(Carbon::parse($lastValuationAt)->startOfDay())) {
            $watch->forceFill([
                'current_market_value' => $data['market_value'],
                'last_valuation_at' => $valuedAt,
            ])->saveQuietly();
        }

        return $valuation;
    }
}
