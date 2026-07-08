<?php

/**
 * =========================================================================
 * AuctionCatalogController — Öffentlicher Auktionskatalog (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Auktionen auf der Tenant-Domain öffentlich zeigen und Online-Gebote
 *   entgegennehmen (nur Online-/Hybrid-Auktionen im Status „Läuft").
 *
 * Sichtbarkeit:
 *   Geplant (Vorschau), Läuft (bietbar) und Abgeschlossen (Ergebnisse) —
 *   Entwürfe und abgesagte Auktionen bleiben unsichtbar (404).
 *
 * Datenschutz:
 *   Bieternamen/-adressen erscheinen NIE öffentlich — der Katalog zeigt
 *   nur Höchstgebot und Anzahl der Gebote.
 *
 * Abhängigkeiten: Tenancy via Routen-Middleware (routes/tenant.php),
 * Gebotslogik in PlaceBidAction, Formalvalidierung in PlaceBidRequest.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Auctions\FinalizeAuctionAction;
use App\Actions\Auctions\PlaceBidAction;
use App\Enums\AuctionLotStatus;
use App\Enums\AuctionStatus;
use App\Http\Requests\PlaceBidRequest;
use App\Http\Requests\WinnerDetailsRequest;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Contact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Throwable;

class AuctionCatalogController extends Controller
{
    /** Öffentlich sichtbare Auktions-Status. */
    private const VISIBLE_STATUSES = [
        AuctionStatus::Scheduled,
        AuctionStatus::Live,
        AuctionStatus::Completed,
    ];

    /**
     * Katalog-Übersicht: laufende zuerst, dann geplante, dann beendete.
     */
    public function index(): View
    {
        $this->startDueAuctions();
        $this->finalizeDueAuctions();

        $auctions = Auction::query()
            ->whereIn('status', array_column(self::VISIBLE_STATUSES, 'value'))
            ->withCount('lots')
            ->orderByRaw(
                "CASE status WHEN 'live' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END"
            )
            ->orderByDesc('starts_at')
            ->get();

        return view('shop.auctions.index', [
            'auctions' => $auctions,
        ]);
    }

    /**
     * Auktionsseite: alle Lose mit Uhr, Schätzpreisen und Höchstgebot.
     */
    public function show(string $auctionId): View
    {
        $auction = $this->visibleAuction($auctionId);

        $lots = $auction->lots()
            ->with(['watch.brand', 'watch.media'])
            ->withCount('bids')
            ->withMax('bids', 'amount')
            ->get();

        return view('shop.auctions.show', [
            'auction' => $auction,
            'lots' => $lots,
        ]);
    }

    /**
     * Los-Detailseite mit Gebotsformular.
     */
    public function lot(string $auctionId, string $lotId): View
    {
        $auction = $this->visibleAuction($auctionId);

        $lot = $auction->lots()
            ->with(['watch.brand', 'watch.caliber', 'watch.media'])
            ->withCount('bids')
            ->findOrFail($lotId);

        return view('shop.auctions.lot', [
            'auction' => $auction,
            'lot' => $lot,
        ]);
    }

    /**
     * Gebot abgeben — fachliche Ablehnungen (Bietfenster, Mindestgebot)
     * erscheinen als Formularfehler am Betrag.
     */
    public function bid(PlaceBidRequest $request, string $auctionId, string $lotId): RedirectResponse
    {
        $auction = $this->visibleAuction($auctionId);

        /** @var AuctionLot $lot */
        $lot = $auction->lots()->findOrFail($lotId);

        try {
            $bid = app(PlaceBidAction::class)->execute($lot, [
                ...$request->validated(),
                'ip_address' => $request->ip(),
            ]);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with(
            'bid_success',
            'Ihr Gebot über '.number_format((float) $bid->amount, 0, ',', '.').' € wurde erfasst.'
        );
    }

    /**
     * Gewinner-Datenseite (signierter Link aus der Zuschlag-Mail):
     * Liefer-/Rechnungsdaten für den Käufer-Kontakt erfassen.
     */
    public function winner(string $auctionId, string $lotId): View
    {
        [$lot, $buyer] = $this->wonLotWithBuyer($auctionId, $lotId);

        return view('shop.auctions.winner', [
            'auction' => $lot->auction,
            'lot' => $lot,
            'watch' => $lot->watch,
            'buyer' => $buyer,
        ]);
    }

    /**
     * Gewinner-Daten speichern — aktualisiert den Käufer-Kontakt.
     */
    public function saveWinner(WinnerDetailsRequest $request, string $auctionId, string $lotId): RedirectResponse
    {
        [, $buyer] = $this->wonLotWithBuyer($auctionId, $lotId);

        $buyer->update($request->validated());

        return back()->with(
            'winner_success',
            'Vielen Dank — Ihre Daten sind bei uns eingegangen. Nach Zahlungseingang versenden wir Ihre Uhr.'
        );
    }

    /**
     * Zugeschlagenes Los inkl. Käufer — 404, wenn das Los nicht
     * zugeschlagen ist oder (noch) kein Käufer hinterlegt wurde.
     *
     * @return array{0: AuctionLot, 1: Contact}
     */
    private function wonLotWithBuyer(string $auctionId, string $lotId): array
    {
        $auction = Auction::query()->findOrFail($auctionId);

        /** @var AuctionLot $lot */
        $lot = $auction->lots()
            ->where('status', AuctionLotStatus::Sold->value)
            ->with(['watch.brand', 'buyer', 'auction'])
            ->findOrFail($lotId);

        $buyer = $lot->buyer;

        abort_if($buyer === null, 404);

        return [$lot, $buyer];
    }

    private function visibleAuction(string $auctionId): Auction
    {
        $auction = Auction::query()
            ->whereIn('status', array_column(self::VISIBLE_STATUSES, 'value'))
            ->findOrFail($auctionId);

        // Pünktlicher Start & pünktliche Abwicklung auch ohne Scheduler:
        // der Seitenaufruf genügt.
        $auction->startIfDue();
        $this->finalizeDueAuctions();

        return $auction->refresh();
    }

    /**
     * Fallback ohne Cron: abgelaufene Auktionen beim Seitenaufruf
     * abwickeln (Zuschlag/Rückgang + Gewinner-Mail). Fehler dürfen die
     * öffentliche Seite nie brechen — nur loggen.
     */
    private function finalizeDueAuctions(): void
    {
        try {
            $due = Auction::query()
                ->where('status', AuctionStatus::Live->value)
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now())
                ->get();

            foreach ($due as $auction) {
                app(FinalizeAuctionAction::class)->execute($auction);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Alle fälligen geplanten Auktionen in einem Rutsch starten —
     * Fallback für den Scheduler (auctions:start-due), damit der
     * Katalog auch lokal ohne laufenden Cron korrekt ist.
     */
    private function startDueAuctions(): void
    {
        Auction::query()
            ->where('status', AuctionStatus::Scheduled->value)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->update(['status' => AuctionStatus::Live->value]);
    }
}
