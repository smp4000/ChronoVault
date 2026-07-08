<?php

/**
 * =========================================================================
 * OnlineAuctionTest — Öffentlicher Auktionskatalog & Online-Gebote (8b)
 * =========================================================================
 *
 * Abgedeckt:
 *   - PlaceBidAction-Guards: Saalauktion, geschlossenes Bietfenster
 *     (nicht "Läuft" / Endzeit überschritten), abgerechnetes Los
 *   - Mindestgebot: Startpreis fürs erste Gebot, danach Höchstgebot +
 *     Erhöhungsschritt; Erhöhungsstaffel
 *   - HTTP: Katalog/Los-Seiten öffentlich, Entwurf unsichtbar (404),
 *     Gebot per POST (Erfolg + Ablehnung als Formularfehler)
 *
 * Muster: tenancy()->end() im finally nach HTTP-Requests.
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Auctions\AddLotToAuctionAction;
use App\Actions\Auctions\PlaceBidAction;
use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Brand;
use App\Models\Watch;

/**
 * Helper: Live-Online-Auktion mit einem offenen Los (Startpreis 1.000 €).
 *
 * @return array{0: Auction, 1: AuctionLot}
 */
function liveOnlineAuctionWithLot(): array
{
    $auction = Auction::factory()->create([
        'venue' => AuctionVenue::Online,
        'status' => AuctionStatus::Live,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
    ]);

    $watch = Watch::factory()->create([
        'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
    ]);

    $lot = app(AddLotToAuctionAction::class)->execute($auction, $watch, [
        'starting_price' => 1000,
    ]);

    return [$auction, $lot];
}

it('guards online bids: venue, bidding window and lot state', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;
            $action = app(PlaceBidAction::class);
            $bidData = [
                'bidder_name' => 'Max Bieter',
                'bidder_email' => 'max@example.test',
                'amount' => 5000,
            ];

            // Saalauktion: keine Online-Gebote
            $saleroom = Auction::factory()->create([
                'venue' => AuctionVenue::Saleroom,
                'status' => AuctionStatus::Live,
            ]);
            $saleroomLot = app(AddLotToAuctionAction::class)->execute(
                $saleroom,
                Watch::factory()->create(['brand_id' => $brandId]),
            );

            expect(fn () => $action->execute($saleroomLot, $bidData))
                ->toThrow(RuntimeException::class, 'keine Online-Gebote');

            // Geplant (noch nicht "Läuft"): Bietfenster zu
            $scheduled = Auction::factory()->create([
                'venue' => AuctionVenue::Online,
                'status' => AuctionStatus::Scheduled,
            ]);
            $scheduledLot = app(AddLotToAuctionAction::class)->execute(
                $scheduled,
                Watch::factory()->create(['brand_id' => $brandId]),
            );

            expect(fn () => $action->execute($scheduledLot, $bidData))
                ->toThrow(RuntimeException::class, 'Bietfenster');

            // Endzeit überschritten: Bietfenster zu
            $ended = Auction::factory()->create([
                'venue' => AuctionVenue::Online,
                'status' => AuctionStatus::Live,
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->subHour(),
            ]);
            $endedLot = app(AddLotToAuctionAction::class)->execute(
                $ended,
                Watch::factory()->create(['brand_id' => $brandId]),
            );

            expect(fn () => $action->execute($endedLot, $bidData))
                ->toThrow(RuntimeException::class, 'Bietfenster');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('enforces minimum bids with increment steps', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            [, $lot] = liveOnlineAuctionWithLot();
            $action = app(PlaceBidAction::class);

            $bidder = fn (float $amount): array => [
                'bidder_name' => 'Max Bieter',
                'bidder_email' => 'max@example.test',
                'amount' => $amount,
            ];

            // Erstes Gebot unter dem Startpreis (1.000 €) → abgelehnt
            expect(fn () => $action->execute($lot, $bidder(900)))
                ->toThrow(RuntimeException::class, 'Mindestgebot von 1.000 €');

            // Startpreis exakt → angenommen
            $first = $action->execute($lot, $bidder(1000));
            expect((float) $first->amount)->toBe(1000.0)
                ->and($lot->highestBidAmount())->toBe(1000.0)
                // 1.000 € → Erhöhungsschritt 100 € → Mindestgebot 1.100 €
                ->and($lot->minimumNextBid())->toBe(1100.0);

            // Unter Höchstgebot + Schritt → abgelehnt
            expect(fn () => $action->execute($lot, $bidder(1050)))
                ->toThrow(RuntimeException::class, 'Mindestgebot von 1.100 €');

            // Deutlich höher → angenommen; nächster Schritt folgt der Staffel
            $second = $action->execute($lot, $bidder(5000));
            expect((float) $second->amount)->toBe(5000.0)
                // 5.000 € → Erhöhungsschritt 500 €
                ->and($lot->minimumNextBid())->toBe(5500.0);

            // Erhöhungsstaffel punktuell prüfen
            expect(AuctionLot::bidIncrementFor(50))->toBe(10.0)
                ->and(AuctionLot::bidIncrementFor(1500))->toBe(100.0)
                ->and(AuctionLot::bidIncrementFor(9999))->toBe(500.0)
                ->and(AuctionLot::bidIncrementFor(60000))->toBe(2500.0);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('serves the public auction catalog and hides drafts', function () {
    $tenant = provisionTenant();

    try {
        $auctionId = null;
        $lotId = null;
        $draftId = null;

        $tenant->run(function () use (&$auctionId, &$lotId, &$draftId) {
            [$auction, $lot] = liveOnlineAuctionWithLot();
            $auctionId = $auction->id;
            $lotId = $lot->id;

            $draftId = Auction::factory()->draft()->create()->id;
        });

        $domain = $tenant->primaryDomain();

        $this->get('http://'.$domain.'/auktionen')
            ->assertOk()
            ->assertSee('Auktionen')
            ->assertSee('Läuft');

        $this->get('http://'.$domain.'/auktionen/'.$auctionId)
            ->assertOk()
            ->assertSee('Los 1')
            ->assertSee('Katalog');

        $this->get('http://'.$domain.'/auktionen/'.$auctionId.'/los/'.$lotId)
            ->assertOk()
            ->assertSee('Gebot abgeben')
            ->assertSee('Mindestgebot')
            ->assertSee('1.000');

        // Entwürfe bleiben öffentlich unsichtbar
        $this->get('http://'.$domain.'/auktionen/'.$draftId)->assertNotFound();
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('accepts online bids via http and rejects too low bids as form errors', function () {
    $tenant = provisionTenant();

    try {
        $auctionId = null;
        $lotId = null;

        $tenant->run(function () use (&$auctionId, &$lotId) {
            [$auction, $lot] = liveOnlineAuctionWithLot();
            $auctionId = $auction->id;
            $lotId = $lot->id;
        });

        $domain = $tenant->primaryDomain();
        $bidUrl = 'http://'.$domain.'/auktionen/'.$auctionId.'/los/'.$lotId.'/bieten';
        $lotUrl = 'http://'.$domain.'/auktionen/'.$auctionId.'/los/'.$lotId;

        // Gültiges Gebot → Erfolgs-Flash + gespeichert
        $this->from($lotUrl)
            ->post($bidUrl, [
                'bidder_name' => 'Erika Mustermann',
                'bidder_email' => 'erika@example.test',
                'amount' => 1200,
            ])
            ->assertRedirect($lotUrl)
            ->assertSessionHas('bid_success');

        // Zu niedriges Folgegebot → Fehler am Betragsfeld, kein Datensatz
        $this->from($lotUrl)
            ->post($bidUrl, [
                'bidder_name' => 'Max Bieter',
                'bidder_email' => 'max@example.test',
                'amount' => 1201,
            ])
            ->assertRedirect($lotUrl)
            ->assertSessionHasErrors('amount');

        $tenant->run(function () use ($lotId) {
            $lot = AuctionLot::findOrFail($lotId);

            expect($lot->bids()->count())->toBe(1)
                ->and($lot->highestBidAmount())->toBe(1200.0)
                ->and($lot->bids()->first()->bidder_name)->toBe('Erika Mustermann')
                ->and($lot->bids()->first()->ip_address)->not->toBeNull();
        });
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});
