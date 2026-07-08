<?php

/**
 * =========================================================================
 * AddLotToAuctionAction — Uhr als Los in eine Auktion einliefern
 * =========================================================================
 *
 * Zweck:
 *   Legt das Los an, MERKT sich den aktuellen Uhren-Status
 *   (previous_watch_status) und setzt die Uhr auf "In Auktion".
 *   Die SettleLotAction stellt den gemerkten Status bei Rückgang oder
 *   Rückzug wieder her — eine Kommissionsuhr kommt als Kommission zurück.
 *
 * Guards (RuntimeException mit deutscher Meldung für die UI):
 *   - Auktion muss Lose annehmen (Entwurf/Geplant/Läuft)
 *   - Uhr darf nicht verkauft sein
 *   - Uhr darf nicht bereits in einem offenen Los stecken
 *
 * Aufrufer: Filament (LotsRelationManager der Auktion).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Auctions;

use App\Enums\AuctionLotStatus;
use App\Enums\WatchStatus;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Watch;
use RuntimeException;

class AddLotToAuctionAction
{
    /**
     * @param  array{lot_number?: int|string|null, starting_price?: float|string|null, estimate_low?: float|string|null, estimate_high?: float|string|null, reserve_price?: float|string|null, notes?: string|null}  $data
     */
    public function execute(Auction $auction, Watch $watch, array $data = []): AuctionLot
    {
        if (! $auction->acceptsLots()) {
            throw new RuntimeException(
                'Diese Auktion nimmt keine Lose mehr an (Status „'.$auction->getAttribute('status')->getLabel().'“).'
            );
        }

        if ($watch->isSold()) {
            throw new RuntimeException('Verkaufte Uhren können nicht eingeliefert werden.');
        }

        $alreadyListed = AuctionLot::query()
            ->where('watch_id', $watch->getKey())
            ->where('status', AuctionLotStatus::Open->value)
            ->exists();

        if ($alreadyListed) {
            throw new RuntimeException('Diese Uhr ist bereits als offenes Los eingeliefert.');
        }

        // Losnummer: explizit übergeben oder fortlaufend (Katalog-Reihenfolge)
        $lotNumber = $data['lot_number']
            ?? ((int) $auction->lots()->withTrashed()->max('lot_number')) + 1;

        $lot = $auction->lots()->create([
            'watch_id' => $watch->getKey(),
            'lot_number' => $lotNumber,
            'status' => AuctionLotStatus::Open,
            // Aktuellen Status merken — außer die Uhr ist schon "In Auktion"
            // (dann würden wir "in_auction" als Restore-Ziel speichern).
            'previous_watch_status' => $watch->getAttribute('status') === WatchStatus::InAuction
                ? null
                : $watch->getAttribute('status'),
            'starting_price' => $data['starting_price'] ?? null,
            'estimate_low' => $data['estimate_low'] ?? null,
            'estimate_high' => $data['estimate_high'] ?? null,
            'reserve_price' => $data['reserve_price'] ?? null,
            'currency' => $auction->currency,
            'notes' => $data['notes'] ?? null,
        ]);

        // saveQuietly: kein Observer-Durchlauf nötig.
        $watch->forceFill(['status' => WatchStatus::InAuction])->saveQuietly();

        return $lot;
    }
}
