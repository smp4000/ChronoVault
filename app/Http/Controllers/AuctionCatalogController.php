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

use App\Actions\Auctions\PlaceBidAction;
use App\Enums\AuctionStatus;
use App\Http\Requests\PlaceBidRequest;
use App\Models\Auction;
use App\Models\AuctionLot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

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

    private function visibleAuction(string $auctionId): Auction
    {
        return Auction::query()
            ->whereIn('status', array_column(self::VISIBLE_STATUSES, 'value'))
            ->findOrFail($auctionId);
    }
}
