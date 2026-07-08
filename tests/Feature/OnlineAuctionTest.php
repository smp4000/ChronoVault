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
use App\Actions\Auctions\SettleLotAction;
use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use App\Mail\BidConfirmationMail;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Watch;
use Illuminate\Support\Facades\Mail;

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

it('sends a binding confirmation mail to the bidder', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Mail::fake();

            [, $lot] = liveOnlineAuctionWithLot();

            app(PlaceBidAction::class)->execute($lot, [
                'bidder_name' => 'Erika Mustermann',
                'bidder_email' => 'erika@example.test',
                'amount' => 1500,
            ]);

            Mail::assertSent(
                BidConfirmationMail::class,
                function (BidConfirmationMail $mail): bool {
                    $mail->assertTo('erika@example.test');

                    // Rendering prüfen: Betrag, Verbindlichkeit, Los-Link
                    $html = $mail->render();

                    return str_contains($html, '1.500 €')
                        && str_contains($html, 'verbindlich')
                        && str_contains($html, 'Los 1')
                        && str_contains($html, '/auktionen/');
                },
            );
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('settles a lot to a bidder and creates or reuses the buyer contact', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $placeBid = app(PlaceBidAction::class);
            $settle = app(SettleLotAction::class);

            // Fall 1: Neuer Bieter → Kontakt wird automatisch angelegt
            [, $lot] = liveOnlineAuctionWithLot();

            $bid = $placeBid->execute($lot, [
                'bidder_name' => 'Erika Mustermann',
                'bidder_email' => 'erika@example.test',
                'bidder_phone' => '+49 170 1234567',
                'amount' => 1500,
            ]);

            $settle->sold($lot, [
                'hammer_price' => 1500,
                'winning_bid_id' => $bid->id,
            ]);

            $contact = Contact::where('email', 'erika@example.test')->firstOrFail();

            expect($contact->first_name)->toBe('Erika')
                ->and($contact->last_name)->toBe('Mustermann')
                ->and($contact->phone)->toBe('+49 170 1234567')
                ->and($lot->refresh()->buyer_contact_id)->toBe($contact->id)
                ->and($lot->watch->transactions()->where('type', 'sale')->firstOrFail()->contact_id)->toBe($contact->id);

            // Fall 2: Bieter ist Stammkunde (gleiche E-Mail) → KEIN Duplikat
            [, $secondLot] = liveOnlineAuctionWithLot();

            $secondBid = $placeBid->execute($secondLot, [
                'bidder_name' => 'Erika M.',
                'bidder_email' => 'erika@example.test',
                'amount' => 2000,
            ]);

            $settle->sold($secondLot, [
                'hammer_price' => 2000,
                'winning_bid_id' => $secondBid->id,
            ]);

            expect(Contact::where('email', 'erika@example.test')->count())->toBe(1)
                ->and($secondLot->refresh()->buyer_contact_id)->toBe($contact->id);
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
