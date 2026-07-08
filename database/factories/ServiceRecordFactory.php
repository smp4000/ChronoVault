<?php

/**
 * =========================================================================
 * ServiceRecordFactory — Test-/Seed-Daten für Servicevorgänge
 * =========================================================================
 *
 * ACHTUNG: Direkte Factory-Erstellung synchronisiert NICHT den
 * Uhren-Status — im Anwendungscode immer die Actions verwenden.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Models\ServiceRecord;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRecord>
 */
class ServiceRecordFactory extends Factory
{
    protected $model = ServiceRecord::class;

    public function definition(): array
    {
        return [
            'watch_id' => Watch::factory(),
            'type' => fake()->randomElement(ServiceType::cases()),
            'status' => ServiceStatus::InProgress,
            'submitted_at' => fake()->dateTimeBetween('-1 year'),
            'cost' => fake()->randomFloat(2, 100, 2000),
            'currency' => 'EUR',
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => ServiceStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
