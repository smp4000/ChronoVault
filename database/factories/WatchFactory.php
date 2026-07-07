<?php

/**
 * =========================================================================
 * WatchFactory — Test-/Seed-Daten für Uhren (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Erzeugt Watch-Datensätze für Tests. ACHTUNG: Die watches-Tabelle
 *   existiert nur in TENANT-Datenbanken — die Factory muss innerhalb
 *   eines Tenant-Kontexts ($tenant->run(...)) verwendet werden.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BraceletMaterial;
use App\Enums\CaseMaterial;
use App\Enums\WatchColor;
use App\Enums\WatchCondition;
use App\Enums\WatchGender;
use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Watch>
 */
class WatchFactory extends Factory
{
    protected $model = Watch::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'caliber_id' => null,
            'model_name' => fake()->words(2, true),
            'reference_number' => strtoupper(fake()->unique()->bothify('###???##')),
            'serial_number' => strtoupper(fake()->bothify('??######')),
            'stock_number' => 'CV-'.fake()->unique()->numberBetween(1000, 99999),
            'production_year' => fake()->numberBetween(1970, (int) date('Y')),
            'condition' => fake()->randomElement(WatchCondition::cases()),
            'status' => WatchStatus::InStock,
            'gender' => fake()->randomElement(WatchGender::cases()),
            'has_box' => fake()->boolean(70),
            'has_papers' => fake()->boolean(70),
            'case_material' => fake()->randomElement(CaseMaterial::cases()),
            'case_diameter_mm' => fake()->randomFloat(1, 28, 45),
            'dial_color' => fake()->randomElement(WatchColor::cases()),
            'bracelet_material' => fake()->randomElement(BraceletMaterial::cases()),
        ];
    }

    public function sold(): static
    {
        return $this->state(['status' => WatchStatus::Sold]);
    }

    public function fullSet(): static
    {
        return $this->state(['has_box' => true, 'has_papers' => true]);
    }
}
