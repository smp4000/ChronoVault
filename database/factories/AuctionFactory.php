<?php

/**
 * =========================================================================
 * AuctionFactory — Test-/Seed-Daten für Auktionen
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use App\Models\Auction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    protected $model = Auction::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 month', '+2 months');

        return [
            'title' => fake()->randomElement(['Frühjahrs', 'Sommer', 'Herbst', 'Winter']).'auktion '.fake()->year().' — Armbanduhren',
            'venue' => fake()->randomElement(AuctionVenue::cases()),
            'location' => fake()->city(),
            'status' => AuctionStatus::Scheduled,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+6 hours'),
            'currency' => 'EUR',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => AuctionStatus::Draft]);
    }

    public function completed(): static
    {
        return $this->state(['status' => AuctionStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => AuctionStatus::Cancelled]);
    }
}
