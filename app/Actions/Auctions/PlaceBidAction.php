<?php

/**
 * =========================================================================
 * PlaceBidAction — Online-Gebot auf ein Auktionslos abgeben (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Validiert und speichert ein Gebot aus dem öffentlichen
 *   Auktionskatalog. Alle Regeln liegen HIER (nicht im Controller):
 *
 * Guards (RuntimeException mit deutscher Meldung für die UI):
 *   - Auktion online-fähig (Online/Hybrid) und Bietfenster offen
 *     (Status "Läuft", Endzeit nicht überschritten)
 *   - Los noch offen (nicht zugeschlagen/zurückgezogen)
 *   - Gebot >= Mindestgebot (Höchstgebot + Erhöhungsschritt bzw.
 *     Startpreis)
 *
 * Race-Schutz:
 *   Zwei gleichzeitige Gebote dürfen das Mindestgebot nicht beide
 *   passieren — die Prüfung läuft in einer DB-Transaktion mit
 *   Sperre auf den Gebot-Zeilen des Loses (lockForUpdate).
 *
 * Aufrufer: AuctionCatalogController (POST /auktionen/.../bieten).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Auctions;

use App\Models\AuctionBid;
use App\Models\AuctionLot;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlaceBidAction
{
    /**
     * @param  array{bidder_name: string, bidder_email: string, bidder_phone?: string|null, amount: float|string, ip_address?: string|null}  $data
     */
    public function execute(AuctionLot $lot, array $data): AuctionBid
    {
        $auction = $lot->auction;

        if (! $auction->allowsOnlineBidding()) {
            throw new RuntimeException('Diese Auktion nimmt keine Online-Gebote entgegen.');
        }

        if (! $auction->isBiddingOpen()) {
            throw new RuntimeException('Das Bietfenster dieser Auktion ist derzeit geschlossen.');
        }

        if (! $lot->isOpen()) {
            throw new RuntimeException('Dieses Los ist nicht mehr verfügbar.');
        }

        return DB::transaction(function () use ($lot, $data): AuctionBid {
            // Sperre auf den Geboten des Loses: paralleles Gebot muss
            // warten und sieht danach das aktualisierte Mindestgebot.
            $lot->bids()->lockForUpdate()->get();

            $minimum = $lot->minimumNextBid();
            $amount = (float) $data['amount'];

            if ($amount < $minimum) {
                throw new RuntimeException(
                    'Ihr Gebot liegt unter dem Mindestgebot von '.number_format($minimum, 0, ',', '.').' €.'
                );
            }

            return $lot->bids()->create([
                'bidder_name' => $data['bidder_name'],
                'bidder_email' => $data['bidder_email'],
                'bidder_phone' => $data['bidder_phone'] ?? null,
                'amount' => $amount,
                'currency' => $lot->currency,
                'ip_address' => $data['ip_address'] ?? null,
            ]);
        });
    }
}
