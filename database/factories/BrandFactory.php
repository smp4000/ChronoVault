<?php

/**
 * =========================================================================
 * BrandFactory — Test-/Seed-Daten für Marken (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Erzeugt Brand-Datensätze für Tests. ACHTUNG: Die brands-Tabelle
 *   existiert nur in TENANT-Datenbanken — die Factory muss innerhalb
 *   eines Tenant-Kontexts ($tenant->run(...)) verwendet werden.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'country' => fake()->country(),
            'founded_year' => fake()->numberBetween(1750, 2020),
            'website' => fake()->url(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
