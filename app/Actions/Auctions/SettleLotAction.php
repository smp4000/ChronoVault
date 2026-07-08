<?php

/**
 * =========================================================================
 * SettleLotAction — Los abrechnen: Zuschlag, Rückgang oder Rückzug
 * =========================================================================
 *
 * Zweck:
 *   - sold():     Zuschlag erfassen → Verkaufsbeleg über RecordSaleAction
 *                 (Modul 5; setzt die Uhr auf "Verkauft"), Los → Zugeschlagen.
 *   - unsold():   Rückgang (kein Gebot über Limit) → Los → Rückgang,
 *                 Uhren-Status-RESTORE aus previous_watch_status.
 *   - withdraw(): Rückzug vor dem Aufruf → Los → Zurückgezogen, Restore.
 *
 * WARUM Restore nur, wenn die Uhr noch "In Auktion" ist:
 *   Wurde der Status zwischenzeitlich anders gesetzt (z. B. Service),
 *   darf die Abrechnung ihn nicht überschreiben — gleiche Semantik wie
 *   CompleteServiceAction (Modul 6).
 *
 * Aufrufer: Filament (Los-Aktionen im LotsRelationManager).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Auctions;

use App\Actions\Transactions\RecordSaleAction;
use App\Enums\AuctionLotStatus;
use App\Enums\WatchStatus;
use App\Models\AuctionLot;
use App\Models\Watch;
use RuntimeException;

class SettleLotAction
{
    public function __construct(
        private readonly RecordSaleAction $recordSale,
    ) {}

    /**
     * Zuschlag: Verkaufsbeleg anlegen und Los abschließen.
     *
     * @param  array{hammer_price: float|string, buyer_contact_id?: string|null, settled_at?: string|\DateTimeInterface|null, payment_method?: string|null, notes?: string|null}  $data
     */
    public function sold(AuctionLot $lot, array $data): AuctionLot
    {
        $this->assertOpen($lot);

        $watch = $lot->watch;
        $settledAt = $data['settled_at'] ?? now();

        // Verkaufsbeleg (Modul 5) — setzt die Uhr auf "Verkauft".
        $this->recordSale->execute($watch, [
            'contact_id' => $data['buyer_contact_id'] ?? null,
            'price' => $data['hammer_price'],
            'transacted_at' => $settledAt,
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => 'Auktionszuschlag — Los '.$lot->lot_number.', „'.$lot->auction->title.'“',
        ]);

        $lot->forceFill([
            'status' => AuctionLotStatus::Sold,
            'hammer_price' => $data['hammer_price'],
            'buyer_contact_id' => $data['buyer_contact_id'] ?? null,
            'settled_at' => $settledAt,
            'notes' => $data['notes'] ?? $lot->notes,
        ])->save();

        return $lot;
    }

    /**
     * Rückgang: kein Zuschlag — Uhren-Status wiederherstellen.
     */
    public function unsold(AuctionLot $lot): AuctionLot
    {
        return $this->close($lot, AuctionLotStatus::Unsold);
    }

    /**
     * Rückzug: Los vor dem Aufruf entnommen — Uhren-Status wiederherstellen.
     */
    public function withdraw(AuctionLot $lot): AuctionLot
    {
        return $this->close($lot, AuctionLotStatus::Withdrawn);
    }

    /**
     * Gemeinsamer Abschluss ohne Zuschlag (Rückgang/Rückzug).
     */
    private function close(AuctionLot $lot, AuctionLotStatus $result): AuctionLot
    {
        $this->assertOpen($lot);

        $lot->forceFill([
            'status' => $result,
            'settled_at' => now(),
        ])->save();

        $this->restoreWatchStatus($lot->watch, $lot);

        return $lot;
    }

    /**
     * Status-Restore — nur wenn die Uhr noch "In Auktion" ist.
     */
    private function restoreWatchStatus(Watch $watch, AuctionLot $lot): void
    {
        if ($watch->getAttribute('status') !== WatchStatus::InAuction) {
            return;
        }

        $watch->forceFill([
            'status' => $lot->getAttribute('previous_watch_status') ?? WatchStatus::InStock,
        ])->saveQuietly();
    }

    private function assertOpen(AuctionLot $lot): void
    {
        if (! $lot->isOpen()) {
            throw new RuntimeException(
                'Dieses Los ist bereits abgerechnet („'.$lot->getAttribute('status')->getLabel().'“).'
            );
        }
    }
}
