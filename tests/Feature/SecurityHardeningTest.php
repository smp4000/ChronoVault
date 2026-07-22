<?php

/**
 * =========================================================================
 * SecurityHardeningTest — Tests der Härtungsmaßnahmen (Audit 2026-07-22)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Security-Header auf jeder Antwort (SecurityHeaders-Middleware)
 *   - DSGVO-Löschkonzept (PrunePersonalDataCommand): IP-Anonymisierung,
 *     Gebots- und Preisvorschlags-Löschfristen
 *   - Seeder-Schutz: Produktion ohne Admin-Zugangsdaten bricht ab
 * =========================================================================
 */

declare(strict_types=1);

use App\Console\Commands\PrunePersonalDataCommand;
use App\Enums\AuctionLotStatus;
use App\Enums\AuctionStatus;
use App\Enums\PriceProposalStatus;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Models\Brand;
use App\Models\PriceProposal;
use App\Models\Watch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('sends security headers on every response', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    // HSTS nur in Produktion — lokal/testing darf der Header NICHT gesetzt
    // sein (würde http://localhost dauerhaft brechen).
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('prunes personal data according to the retention rules', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            $auction = Auction::factory()->create(['status' => AuctionStatus::Completed]);

            $closedLot = AuctionLot::factory()->create([
                'auction_id' => $auction->id,
                'watch_id' => $watch->id,
                'status' => AuctionLotStatus::Unsold,
            ]);

            $makeBid = function (AuctionLot $lot, int $ageDays) {
                $bid = AuctionBid::create([
                    'auction_lot_id' => $lot->id,
                    'bidder_name' => 'Test Bieter',
                    'bidder_email' => 'bieter@example.test',
                    'amount' => 1000,
                    'currency' => 'EUR',
                    'ip_address' => '203.0.113.7',
                ]);

                DB::table('auction_bids')->where('id', $bid->id)->update([
                    'created_at' => now()->subDays($ageDays),
                ]);

                return $bid->id;
            };

            // Regel 2: Gebot eines geschlossenen Loses, älter als 180 Tage → weg
            $ancientBidId = $makeBid($closedLot, PrunePersonalDataCommand::BID_RETENTION_DAYS + 20);
            // Regel 1: Gebot älter als 30 Tage → IP anonymisiert, Zeile bleibt
            $agedBidId = $makeBid($closedLot, PrunePersonalDataCommand::IP_RETENTION_DAYS + 10);
            // Frisches Gebot → komplett unangetastet
            $freshBidId = $makeBid($closedLot, 1);

            // Regel 3: abgeschlossener Preisvorschlag, 100 Tage alt → endgültig weg
            $decidedProposal = PriceProposal::create([
                'watch_id' => $watch->id,
                'name' => 'Alter Kunde',
                'email' => 'alt@example.test',
                'proposed_price' => 900,
                'status' => PriceProposalStatus::Declined,
            ]);
            // Offener (countered) Vorschlag gleichen Alters → bleibt
            $openProposal = PriceProposal::create([
                'watch_id' => $watch->id,
                'name' => 'Offener Kunde',
                'email' => 'offen@example.test',
                'proposed_price' => 950,
                'status' => PriceProposalStatus::Countered,
            ]);

            DB::table('price_proposals')
                ->whereIn('id', [$decidedProposal->id, $openProposal->id])
                ->update(['updated_at' => now()->subDays(PrunePersonalDataCommand::PROPOSAL_RETENTION_DAYS + 10)]);

            Artisan::call('chronovault:prune-personal-data');

            expect(AuctionBid::whereKey($ancientBidId)->exists())->toBeFalse()
                ->and(AuctionBid::findOrFail($agedBidId)->ip_address)->toBeNull()
                ->and(AuctionBid::findOrFail($freshBidId)->ip_address)->toBe('203.0.113.7')
                ->and(PriceProposal::withTrashed()->whereKey($decidedProposal->id)->exists())->toBeFalse()
                ->and(PriceProposal::whereKey($openProposal->id)->exists())->toBeTrue();
        });
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('refuses to seed the central admin in production without credentials', function () {
    // Umgebung temporär auf "production" stellen — der Seeder muss ohne
    // CENTRAL_ADMIN_EMAIL/PASSWORD hart abbrechen (Default-Admin-Falle).
    // Direkter Aufruf statt $this->seed(): die Artisan-Schicht würde die
    // Exception abfangen und nur als Exit-Code melden.
    $this->app['env'] = 'production';

    try {
        expect(fn () => app(Database\Seeders\DatabaseSeeder::class)->run())
            ->toThrow(RuntimeException::class);
    } finally {
        $this->app['env'] = 'testing';
    }
});
