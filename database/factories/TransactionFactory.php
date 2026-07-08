<?php

/**
 * =========================================================================
 * TransactionFactory — Test-/Seed-Daten für Belege (Tenant-Datenbank)
 * =========================================================================
 *
 * ACHTUNG: Direkte Factory-Erstellung synchronisiert NICHT den
 * Uhren-Status — im Anwendungscode immer die Record*-Actions verwenden.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'watch_id' => Watch::factory(),
            'type' => TransactionType::Purchase,
            'price' => fake()->randomFloat(2, 500, 50000),
            'currency' => 'EUR',
            'transacted_at' => fake()->dateTimeBetween('-2 years'),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
        ];
    }

    public function sale(): static
    {
        return $this->state(['type' => TransactionType::Sale]);
    }
}
