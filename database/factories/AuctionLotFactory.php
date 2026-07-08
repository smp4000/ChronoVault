<?php

/**
 * =========================================================================
 * AuctionLotFactory — Test-/Seed-Daten für Auktionslose
 * =========================================================================
 *
 * ACHTUNG: Direkte Factory-Erstellung synchronisiert NICHT den
 * Uhren-Status — im Anwendungscode immer die Actions verwenden
 * (AddLotToAuctionAction / SettleLotAction).
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuctionLotStatus;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuctionLot>
 */
class AuctionLotFactory extends Factory
{
    protected $model = AuctionLot::class;

    public function definition(): array
    {
        $estimateLow = fake()->numberBetween(10, 200) * 100;

        return [
            'auction_id' => Auction::factory(),
            'watch_id' => Watch::factory(),
            'lot_number' => fake()->unique()->numberBetween(1, 9999),
            'status' => AuctionLotStatus::Open,
            'starting_price' => $estimateLow * 0.8,
            'estimate_low' => $estimateLow,
            'estimate_high' => $estimateLow * 1.5,
            'reserve_price' => $estimateLow * 0.9,
            'currency' => 'EUR',
        ];
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AuctionLotStatus::Sold,
            'hammer_price' => ($attributes['estimate_low'] ?? 1000) * 1.2,
            'settled_at' => now(),
        ]);
    }

    public function unsold(): static
    {
        return $this->state([
            'status' => AuctionLotStatus::Unsold,
            'settled_at' => now(),
        ]);
    }
}
