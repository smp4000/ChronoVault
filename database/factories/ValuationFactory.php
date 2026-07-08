<?php

/**
 * =========================================================================
 * ValuationFactory — Test-/Seed-Daten für Bewertungen (Tenant-Datenbank)
 * =========================================================================
 *
 * ACHTUNG: Direkte Factory-Erstellung pflegt NICHT den Schnellzugriff
 * an der Uhr — im Anwendungscode immer die RecordValuationAction nutzen.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ValuationSource;
use App\Models\Valuation;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Valuation>
 */
class ValuationFactory extends Factory
{
    protected $model = Valuation::class;

    public function definition(): array
    {
        $value = fake()->randomFloat(2, 1000, 60000);

        return [
            'watch_id' => Watch::factory(),
            'source' => ValuationSource::Manual,
            'market_value' => $value,
            'value_low' => $value * 0.9,
            'value_high' => $value * 1.1,
            'currency' => 'EUR',
            'valued_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
