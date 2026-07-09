<?php

/**
 * =========================================================================
 * FinalizeAuctionAction — Auktionsende automatisch abwickeln (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Wenn eine Online-/Hybrid-Auktion ihr Ende erreicht hat, wird jedes
 *   offene Los automatisch abgerechnet:
 *   - Höchstgebot vorhanden UND Limit (reserve_price) erreicht bzw.
 *     kein Limit gesetzt → ZUSCHLAG an den Höchstbietenden
 *     (SettleLotAction::sold: Verkaufsbeleg, Käufer-Kontakt, Uhr
 *     „Verkauft") + Gewinner-Mail mit Zahlungsinfos/GiroCode und
 *     signiertem Link zur Datenerfassung (AuctionWonMail).
 *   - sonst (kein Gebot / unter Limit) → RÜCKGANG (Status-Restore).
 *   Nach dem letzten Los schließt die Auktion automatisch
 *   (completeIfFullySettled in der SettleLotAction).
 *
 * Aufrufer: auctions:finalize-due (Scheduler, jede Minute) und der
 * öffentliche Katalog als Fallback ohne Cron (AuctionCatalogController).
 * Beide Wege sind idempotent — abgerechnete Lose sind nicht mehr offen.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Auctions;

use App\Enums\AuctionStatus;
use App\Mail\AuctionWonMail;
use App\Models\Auction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FinalizeAuctionAction
{
    public function __construct(
        private readonly SettleLotAction $settle,
    ) {}

    /**
     * @return array{sold: int, unsold: int}
     */
    public function execute(Auction $auction): array
    {
        $result = ['sold' => 0, 'unsold' => 0];

        // Nur zeitgesteuerte Online-/Hybrid-Auktionen, deren Ende
        // erreicht ist — Saalauktionen werden manuell abgerechnet.
        $endsAt = $auction->getAttribute('ends_at');

        if (! $auction->allowsOnlineBidding()
            || ! $endsAt instanceof Carbon
            || $endsAt->isFuture()) {
            return $result;
        }

        // Atomarer Claim gegen Doppel-Abwicklung: Scheduler, Seiten-Fallback
        // und Status-Polling können gleichzeitig eintreffen — nur der
        // Prozess, der dieses UPDATE gewinnt, wickelt ab (verhindert
        // doppelte Zuschläge und doppelte Gewinner-Mails).
        $claimed = Auction::query()
            ->whereKey($auction->getKey())
            ->where('status', AuctionStatus::Live->value)
            ->update(['status' => AuctionStatus::Completed->value]);

        if ($claimed === 0) {
            return $result;
        }

        $auction->refresh();

        foreach ($auction->lots()->get() as $lot) {
            if (! $lot->isOpen()) {
                continue;
            }

            $topBid = $lot->bids()->first();
            $reserve = $lot->getAttribute('reserve_price');

            $reserveMet = $topBid !== null
                && ($reserve === null || (float) $topBid->amount >= (float) $reserve);

            if (! $reserveMet) {
                // Kein Gebot oder Limit verfehlt → Rückgang (Status-Restore)
                $this->settle->unsold($lot);
                $result['unsold']++;

                continue;
            }

            $this->settle->sold($lot, [
                'hammer_price' => (float) $topBid->amount,
                'winning_bid_id' => $topBid->getKey(),
                'settled_at' => $endsAt,
                'notes' => 'Automatischer Zuschlag bei Auktionsende.',
            ]);
            $result['sold']++;

            // Gewinner-Mail — ein Mail-Fehler darf die Abwicklung der
            // übrigen Lose nie stoppen (nur loggen).
            try {
                Mail::to($topBid->bidder_email)
                    ->send(new AuctionWonMail($lot->refresh(), $topBid));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $result;
    }
}
