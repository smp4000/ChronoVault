<?php

/**
 * =========================================================================
 * AuctionFinalizeTest — Automatische Abwicklung bei Auktionsende (8b)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Zuschlag an den Höchstbietenden NUR bei erreichtem Limit;
 *     unter Limit / ohne Gebot → Rückgang mit Status-Restore
 *   - Auktion nach Abwicklung automatisch „Abgeschlossen"
 *   - Gewinner-Mail mit Zahlungsinfos (IBAN aus Betriebsdaten) und
 *     signiertem Daten-Link
 *   - Scheduler-Befehl auctions:finalize-due
 *   - Gewinner-Datenseite: signierter Link nötig (403 ohne),
 *     Formular aktualisiert den Käufer-Kontakt
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Auctions\AddLotToAuctionAction;
use App\Actions\Auctions\FinalizeAuctionAction;
use App\Actions\Auctions\PlaceBidAction;
use App\Enums\AuctionLotStatus;
use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use App\Enums\WatchStatus;
use App\Mail\AuctionNotAwardedMail;
use App\Mail\AuctionWonMail;
use App\Models\Auction;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Watch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('finalizes ended auctions: hammer only when the reserve is met', function () {
    $tenant = provisionTenant();

    // Betriebsdaten (zentrales data-JSON) — vollständig, damit beim
    // Zuschlag auch die Rechnung erstellt und angehängt werden kann.
    $tenant->update([
        'bank_account_holder' => 'Test Uhrenhandel GmbH',
        'bank_iban' => 'DE02120300000000202051',
        'bank_bic' => 'BYLADEM1001',
        'company_street' => 'Uhrmacherweg 1',
        'company_postal_code' => '10115',
        'company_city' => 'Berlin',
        'tax_number' => '12/345/67890',
    ]);

    try {
        $tenant->run(function () {
            Mail::fake();

            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;
            $addLot = app(AddLotToAuctionAction::class);
            $placeBid = app(PlaceBidAction::class);

            $auction = Auction::factory()->create([
                'venue' => AuctionVenue::Online,
                'status' => AuctionStatus::Live,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMinute(), // Bietfenster noch offen
            ]);

            // Los 1: Gebot ÜBER Limit → Zuschlag
            $wonLot = $addLot->execute($auction, Watch::factory()->create(['brand_id' => $brandId]), [
                'starting_price' => 1000,
                'reserve_price' => 2000,
            ]);
            $placeBid->execute($wonLot, [
                'bidder_name' => 'Erika Mustermann',
                'bidder_email' => 'erika@example.test',
                'amount' => 2500,
            ]);

            // Los 2: Gebot UNTER Limit → Rückgang (Kommission bleibt Kommission)
            $reservedLot = $addLot->execute($auction, Watch::factory()->create([
                'brand_id' => $brandId,
                'status' => WatchStatus::Consignment,
            ]), [
                'starting_price' => 1000,
                'reserve_price' => 5000,
            ]);
            $placeBid->execute($reservedLot, [
                'bidder_name' => 'Max Bieter',
                'bidder_email' => 'max@example.test',
                'amount' => 1200,
            ]);

            // Los 3: kein Gebot → Rückgang
            $silentLot = $addLot->execute($auction, Watch::factory()->create(['brand_id' => $brandId]));

            // Auktionsende erreicht
            $auction->forceFill(['ends_at' => now()->subMinute()])->saveQuietly();

            $result = app(FinalizeAuctionAction::class)->execute($auction->refresh());

            expect($result)->toBe(['sold' => 1, 'unsold' => 2])
                ->and($wonLot->refresh()->status)->toBe(AuctionLotStatus::Sold)
                ->and((float) $wonLot->hammer_price)->toBe(2500.0)
                ->and($wonLot->watch->refresh()->status)->toBe(WatchStatus::Sold)
                ->and($reservedLot->refresh()->status)->toBe(AuctionLotStatus::Unsold)
                ->and($reservedLot->watch->refresh()->status)->toBe(WatchStatus::Consignment)
                ->and($silentLot->refresh()->status)->toBe(AuctionLotStatus::Unsold)
                // Alle Lose abgerechnet → Auktion abgeschlossen
                ->and($auction->refresh()->status)->toBe(AuctionStatus::Completed);

            // Gewinner-Mail: nur an den Gewinner, mit Zahlungsdaten + Link
            // + automatisch erstellter Rechnung (PDF-Anhang, ZUGFeRD)
            Mail::assertSent(AuctionWonMail::class, 1);
            Mail::assertSent(AuctionWonMail::class, function (AuctionWonMail $mail): bool {
                $mail->assertTo('erika@example.test');

                $html = $mail->render();

                return str_contains($html, 'Herzlichen Glückwunsch')
                    && str_contains($html, '2.500,00')
                    && str_contains($html, 'DE02 1203')
                    && str_contains($html, 'BYLADEM1001')
                    && str_contains($html, '/gewinner')
                    && str_contains($html, 'signature=')
                    && $mail->invoice !== null
                    && str_starts_with($mail->invoice->invoice_number, 'RE-')
                    && str_contains($html, $mail->invoice->invoice_number)
                    && $mail->attachments() !== [];
            });

            // Verlierer bekommt KEINE Zuschlag-Mail
            Mail::assertNotSent(AuctionWonMail::class, fn (AuctionWonMail $mail): bool => $mail->hasTo('max@example.test'));

            // … aber die Kein-Zuschlag-Mail (Limit verfehlt, Limit NIE genannt);
            // das Los ohne Gebote löst keine solche Mail aus.
            Mail::assertSent(AuctionNotAwardedMail::class, 1);
            Mail::assertSent(AuctionNotAwardedMail::class, function (AuctionNotAwardedMail $mail): bool {
                $mail->assertTo('max@example.test');

                $html = $mail->render();

                return str_contains($html, 'kein Zuschlag')
                    && str_contains($html, '1.200')
                    && ! str_contains($html, '5.000');
            });
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('finalizes due auctions via the scheduler command', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Mail::fake();

            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;

            $auction = Auction::factory()->create([
                'venue' => AuctionVenue::Online,
                'status' => AuctionStatus::Live,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMinute(),
            ]);
            $lot = app(AddLotToAuctionAction::class)->execute(
                $auction,
                Watch::factory()->create(['brand_id' => $brandId]),
                ['starting_price' => 500],
            );
            app(PlaceBidAction::class)->execute($lot, [
                'bidder_name' => 'Erika Mustermann',
                'bidder_email' => 'erika@example.test',
                'amount' => 800,
            ]);

            $auction->forceFill(['ends_at' => now()->subMinute()])->saveQuietly();

            Artisan::call('auctions:finalize-due');

            expect($lot->refresh()->status)->toBe(AuctionLotStatus::Sold)
                ->and($auction->refresh()->status)->toBe(AuctionStatus::Completed);

            Mail::assertSent(AuctionWonMail::class, 1);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('lets the winner submit shipping details via the signed link only', function () {
    $tenant = provisionTenant();

    try {
        $signedUrl = null;
        $plainUrl = null;
        $buyerId = null;

        $tenant->run(function () use (&$signedUrl, &$plainUrl, &$buyerId, $tenant) {
            Mail::fake();

            $auction = Auction::factory()->create([
                'venue' => AuctionVenue::Online,
                'status' => AuctionStatus::Live,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMinute(),
            ]);
            $lot = app(AddLotToAuctionAction::class)->execute(
                $auction,
                Watch::factory()->create(['brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id]),
                ['starting_price' => 500],
            );
            app(PlaceBidAction::class)->execute($lot, [
                'bidder_name' => 'Erika Mustermann',
                'bidder_email' => 'erika@example.test',
                'amount' => 800,
            ]);

            $auction->forceFill(['ends_at' => now()->subMinute()])->saveQuietly();
            app(FinalizeAuctionAction::class)->execute($auction->refresh());

            $buyerId = $lot->refresh()->buyer_contact_id;

            // Signierte URL auf der Tenant-Domain erzeugen (wie die Mail)
            URL::forceRootUrl('http://'.$tenant->primaryDomain());
            $signedUrl = URL::temporarySignedRoute(
                'shop.auctions.winner',
                now()->addDays(14),
                ['auction' => $auction->id, 'lot' => $lot->id],
            );
            $plainUrl = route('shop.auctions.winner', ['auction' => $auction->id, 'lot' => $lot->id]);
        });

        // Ohne gültige Signatur: kein Zugriff
        $this->get($plainUrl)->assertForbidden();

        // Mit Signatur: Formular mit Glückwunsch
        $this->get($signedUrl)
            ->assertOk()
            ->assertSee('Herzlichen Glückwunsch')
            ->assertSee('Daten absenden');

        // Daten absenden → Käufer-Kontakt aktualisiert
        $this->from($signedUrl)
            ->post($signedUrl, [
                'first_name' => 'Erika',
                'last_name' => 'Mustermann',
                'street' => 'Musterweg 12',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'Deutschland',
                'phone' => '+49 30 123456',
            ])
            ->assertRedirect()
            ->assertSessionHas('winner_success');

        $tenant->run(function () use ($buyerId) {
            $buyer = Contact::findOrFail($buyerId);

            expect($buyer->street)->toBe('Musterweg 12')
                ->and($buyer->postal_code)->toBe('12345')
                ->and($buyer->city)->toBe('Berlin');
        });
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});
