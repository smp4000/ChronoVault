<?php

/**
 * =========================================================================
 * CaliberFactory — Test-/Seed-Daten für Kaliber (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Erzeugt Caliber-Datensätze für Tests. ACHTUNG: Die calibers-Tabelle
 *   existiert nur in TENANT-Datenbanken — die Factory muss innerhalb
 *   eines Tenant-Kontexts ($tenant->run(...)) verwendet werden.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Brand;
use App\Models\Caliber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caliber>
 */
class CaliberFactory extends Factory
{
    protected $model = Caliber::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => 'Cal. '.fake()->unique()->numberBetween(1000, 99999),
            'movement_type' => fake()->randomElement(MovementType::cases()),
            'power_reserve_hours' => fake()->numberBetween(38, 120),
            'frequency_vph' => fake()->randomElement([18000, 21600, 25200, 28800, 36000]),
            'jewels' => fake()->numberBetween(17, 40),
            'diameter_mm' => fake()->randomFloat(1, 20, 40),
            'is_active' => true,
        ];
    }

    public function manual(): static
    {
        return $this->state(['movement_type' => MovementType::Manual]);
    }
}
