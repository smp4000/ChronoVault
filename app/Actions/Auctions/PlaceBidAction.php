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

use App\Mail\BidConfirmationMail;
use App\Mail\OutbidMail;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class PlaceBidAction
{
    /**
     * @param  array{bidder_name: string, bidder_email: string, bidder_phone?: string|null, amount: float|string, ip_address?: string|null}  $data
     */
    public function execute(AuctionLot $lot, array $data): AuctionBid
    {
        $auction = $lot->auction;

        // Pünktlicher Start auch ohne Scheduler: Ist die Startzeit einer
        // geplanten Auktion erreicht, gilt sie ab jetzt als "Läuft".
        $auction->startIfDue();

        if (! $auction->allowsOnlineBidding()) {
            throw new RuntimeException('Diese Auktion nimmt keine Online-Gebote entgegen.');
        }

        if (! $auction->isBiddingOpen()) {
            throw new RuntimeException('Das Bietfenster dieser Auktion ist derzeit geschlossen.');
        }

        if (! $lot->isOpen()) {
            throw new RuntimeException('Dieses Los ist nicht mehr verfügbar.');
        }

        /** @var array{0: AuctionBid, 1: AuctionBid|null} $result */
        $result = DB::transaction(function () use ($lot, $data): array {
            // Sperre auf dem Höchstgebot des Loses: paralleles Gebot muss
            // warten und sieht danach das aktualisierte Mindestgebot.
            // Der bisherige Höchstbietende wird HIER (unter der Sperre)
            // festgehalten — er bekommt die Überboten-Mail.
            $previousHighest = $lot->bids()->lockForUpdate()->first();

            $minimum = $lot->minimumNextBid();
            $amount = (float) $data['amount'];

            if ($amount < $minimum) {
                throw new RuntimeException(
                    'Ihr Gebot liegt unter dem Mindestgebot von '.number_format($minimum, 0, ',', '.').' €.'
                );
            }

            $bid = $lot->bids()->create([
                'bidder_name' => $data['bidder_name'],
                'bidder_email' => $data['bidder_email'],
                'bidder_phone' => $data['bidder_phone'] ?? null,
                'amount' => $amount,
                'currency' => $lot->currency,
                'ip_address' => $data['ip_address'] ?? null,
            ]);

            return [$bid, $previousHighest];
        });

        [$bid, $previousHighest] = $result;

        // Mails NACH der Transaktion (Gebot ist sicher gespeichert);
        // ein Mail-Fehler darf das Gebot nie verhindern — nur loggen.
        try {
            Mail::to($bid->bidder_email)->send(new BidConfirmationMail($bid));
        } catch (Throwable $exception) {
            report($exception);
        }

        // Überboten-Mail an den abgelösten Höchstbietenden — außer er
        // hat sein eigenes Gebot erhöht (gleiche E-Mail-Adresse).
        if ($previousHighest !== null
            && strcasecmp($previousHighest->bidder_email, $bid->bidder_email) !== 0) {
            try {
                Mail::to($previousHighest->bidder_email)
                    ->send(new OutbidMail($previousHighest, $bid));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $bid;
    }
}
