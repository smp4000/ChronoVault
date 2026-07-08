<?php

/**
 * =========================================================================
 * AuctionTest — Auktionen & Lose (Modul 8)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Einliefern: Los angelegt, Losnummer fortlaufend, Status gemerkt,
 *     Uhr → "In Auktion"; Guards (abgeschlossene Auktion, verkaufte Uhr,
 *     Doppel-Einlieferung)
 *   - Zuschlag: Verkaufsbeleg (Modul 5) + Uhr "Verkauft" + Käufer am Los
 *   - Rückgang/Rückzug: Status-RESTORE (Kommission bleibt Kommission);
 *     kein Restore bei zwischenzeitlicher Status-Änderung
 *   - Berechtigungen (auctions.*) je Rolle
 *   - Referenz-Schutz: Auktion mit offenen Losen nicht löschbar,
 *     Käufer-Kontakt mit Auktionskäufen nicht löschbar
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Auctions\AddLotToAuctionAction;
use App\Actions\Auctions\SettleLotAction;
use App\Enums\AuctionLotStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\WatchStatus;
use App\Models\Auction;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\User;
use App\Models\Watch;

it('adds lots with sequential numbers and moves the watch into auction', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;
            $auction = Auction::factory()->create();

            // Kommissionsuhr — muss nach Rückgang wieder Kommission sein!
            $consignment = Watch::factory()->create([
                'brand_id' => $brandId,
                'status' => WatchStatus::Consignment,
            ]);
            $inStock = Watch::factory()->create(['brand_id' => $brandId]);

            $lotOne = app(AddLotToAuctionAction::class)->execute($auction, $consignment, [
                'estimate_low' => 5000,
                'estimate_high' => 7000,
                'reserve_price' => 4500,
            ]);
            $lotTwo = app(AddLotToAuctionAction::class)->execute($auction, $inStock);

            expect($lotOne->lot_number)->toBe(1)
                ->and($lotTwo->lot_number)->toBe(2)
                ->and($lotOne->status)->toBe(AuctionLotStatus::Open)
                ->and($lotOne->previous_watch_status)->toBe(WatchStatus::Consignment)
                ->and($consignment->refresh()->status)->toBe(WatchStatus::InAuction)
                ->and($inStock->refresh()->status)->toBe(WatchStatus::InAuction);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('guards lot intake: closed auctions, sold watches, duplicate listings', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;
            $action = app(AddLotToAuctionAction::class);

            // Abgeschlossene Auktion nimmt keine Lose an
            $completed = Auction::factory()->completed()->create();
            $watch = Watch::factory()->create(['brand_id' => $brandId]);

            expect(fn () => $action->execute($completed, $watch))
                ->toThrow(RuntimeException::class, 'nimmt keine Lose mehr an');

            // Verkaufte Uhr kann nicht eingeliefert werden
            $auction = Auction::factory()->create();
            $sold = Watch::factory()->create([
                'brand_id' => $brandId,
                'status' => WatchStatus::Sold,
            ]);

            expect(fn () => $action->execute($auction, $sold))
                ->toThrow(RuntimeException::class, 'Verkaufte Uhren');

            // Doppel-Einlieferung (auch in eine ANDERE Auktion) blockiert
            $action->execute($auction, $watch);
            $otherAuction = Auction::factory()->create();

            expect(fn () => $action->execute($otherAuction, $watch))
                ->toThrow(RuntimeException::class, 'bereits als offenes Los');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('settles a sold lot with a sale transaction and marks the watch as sold', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'purchase_price' => 8000,
            ]);
            $auction = Auction::factory()->create();
            $buyer = Contact::factory()->create();

            $lot = app(AddLotToAuctionAction::class)->execute($auction, $watch);

            app(SettleLotAction::class)->sold($lot, [
                'hammer_price' => 12500,
                'buyer_contact_id' => $buyer->id,
            ]);

            $lot->refresh();

            // Der Observer legt für purchase_price bereits einen
            // Ankauf-Beleg an — hier interessiert nur der Verkauf.
            $sale = $watch->transactions()
                ->where('type', TransactionType::Sale->value)
                ->firstOrFail();

            expect($lot->status)->toBe(AuctionLotStatus::Sold)
                ->and($lot->hammer_price)->toBe('12500.00')
                ->and($lot->buyer_contact_id)->toBe($buyer->id)
                ->and($watch->refresh()->status)->toBe(WatchStatus::Sold)
                ->and($sale->type)->toBe(TransactionType::Sale)
                ->and($sale->price)->toBe('12500.00')
                ->and($sale->contact_id)->toBe($buyer->id)
                ->and($sale->notes)->toContain('Los 1');

            // Bereits abgerechnete Lose sind gesperrt
            expect(fn () => app(SettleLotAction::class)->unsold($lot))
                ->toThrow(RuntimeException::class, 'bereits abgerechnet');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('restores the previous watch status on unsold and withdrawn lots', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;
            $auction = Auction::factory()->create();

            // Rückgang: Kommissionsuhr kommt als Kommission zurück
            $consignment = Watch::factory()->create([
                'brand_id' => $brandId,
                'status' => WatchStatus::Consignment,
            ]);
            $lot = app(AddLotToAuctionAction::class)->execute($auction, $consignment);
            app(SettleLotAction::class)->unsold($lot);

            expect($lot->refresh()->status)->toBe(AuctionLotStatus::Unsold)
                ->and($consignment->refresh()->status)->toBe(WatchStatus::Consignment);

            // Rückzug: Lageruhr kommt an Lager zurück
            $inStock = Watch::factory()->create(['brand_id' => $brandId]);
            $withdrawnLot = app(AddLotToAuctionAction::class)->execute($auction, $inStock);
            app(SettleLotAction::class)->withdraw($withdrawnLot);

            expect($withdrawnLot->refresh()->status)->toBe(AuctionLotStatus::Withdrawn)
                ->and($inStock->refresh()->status)->toBe(WatchStatus::InStock);

            // Kein Restore, wenn der Status zwischenzeitlich geändert wurde
            $changed = Watch::factory()->create(['brand_id' => $brandId]);
            $changedLot = app(AddLotToAuctionAction::class)->execute($auction, $changed);
            $changed->refresh()->forceFill(['status' => WatchStatus::InService])->saveQuietly();
            app(SettleLotAction::class)->unsold($changedLot);

            expect($changed->refresh()->status)->toBe(WatchStatus::InService);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('grants auction permissions according to role semantics', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $employee = User::factory()->create();
            $employee->assignRole(UserRole::Employee->value);

            $viewer = User::factory()->create();
            $viewer->assignRole(UserRole::Viewer->value);

            expect($employee->can('auctions.create'))->toBeTrue()
                ->and($employee->can('auctions.update'))->toBeTrue()
                ->and($employee->can('auctions.delete'))->toBeFalse()
                ->and($viewer->can('auctions.view'))->toBeTrue()
                ->and($viewer->can('auctions.create'))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('protects auctions with open lots and buyer contacts from deletion', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $owner = User::firstOrFail();
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;

            $auction = Auction::factory()->create();
            $watch = Watch::factory()->create(['brand_id' => $brandId]);
            $buyer = Contact::factory()->create();

            $lot = app(AddLotToAuctionAction::class)->execute($auction, $watch);

            // Offenes Los → Auktion nicht löschbar
            expect($owner->can('delete', $auction))->toBeFalse();

            app(SettleLotAction::class)->sold($lot, [
                'hammer_price' => 9000,
                'buyer_contact_id' => $buyer->id,
            ]);

            // Alle Lose abgerechnet → löschbar; Käufer aber referenziert
            expect($owner->can('delete', $auction->refresh()))->toBeTrue()
                ->and($owner->can('delete', $buyer))->toBeFalse()
                // Zugeschlagene Lose sind Beleg-Historie — nicht löschbar
                ->and($owner->can('delete', $lot->refresh()))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});
