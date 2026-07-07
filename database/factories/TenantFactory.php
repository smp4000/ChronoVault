<?php

/**
 * =========================================================================
 * TenantFactory — Test-/Seed-Daten für Mandanten
 * =========================================================================
 *
 * Zweck:
 *   Erzeugt Tenant-Datensätze für Tests und Seeder.
 *
 * ACHTUNG:
 *   Tenant::create() feuert die komplette Provisioning-Pipeline
 *   (Datenbank anlegen + migrieren + seeden)! In Tests, die keine echte
 *   Tenant-DB brauchen, das Event-System unterdrücken oder gezielt nur
 *   make() statt create() verwenden.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'status' => TenantStatus::Active,
        ];
    }

    public function trial(): static
    {
        return $this->state(['status' => TenantStatus::Trial]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => TenantStatus::Suspended]);
    }
}
