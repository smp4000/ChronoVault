<?php

/**
 * =========================================================================
 * PrunePersonalDataCommand — Automatisiertes DSGVO-Löschkonzept (je Tenant)
 * =========================================================================
 *
 * Zweck:
 *   Setzt die Speicherbegrenzung (Art. 5 Abs. 1 lit. e DSGVO) technisch
 *   durch. Vor diesem Command (Audit 2026-07-22) existierte KEIN
 *   automatisiertes Löschkonzept — personenbezogene Daten wuchsen
 *   unbegrenzt, obwohl das Preisvorschlag-Formular die Löschung sogar
 *   ausdrücklich zusagt.
 *
 * Löschregeln (Fristen als Klassen-Konstanten, bewusst konservativ):
 *   1. auction_bids.ip_address → nach 30 Tagen anonymisiert (NULL).
 *      Die IP dient allein der Missbrauchs-Nachvollziehbarkeit rund um
 *      die Auktion — dafür reichen 30 Tage locker aus.
 *   2. auction_bids (komplette Zeile) → nach 180 Tagen gelöscht, sofern
 *      das Los nicht mehr offen ist. Gebotshistorie (Name/E-Mail/Telefon)
 *      ist nach abgeschlossener Auktion + Widerrufs-/Reklamationsfenster
 *      nicht mehr erforderlich; der Zuschlag selbst lebt als Transaktion/
 *      Rechnung weiter (gesetzliche Aufbewahrung, eigene Rechtsgrundlage).
 *   3. price_proposals → 90 Tage nach Abschluss (accepted/declined)
 *      endgültig gelöscht (inkl. Soft-Deleted). Damit wird die Zusage
 *      „Daten werden nach abgeschlossener Bearbeitung gelöscht" aus dem
 *      Formular technisch eingehalten; 90 Tage decken Rückfragen ab.
 *
 * BEWUSST NICHT gelöscht:
 *   contacts/transactions/invoices — steuer- und handelsrechtliche
 *   Aufbewahrungspflichten (§ 147 AO: bis 10 Jahre) gehen vor; deren
 *   Fristenverwaltung ist ein eigener TODO (docs/SECURITY.md).
 *
 * Nutzung:
 *   php artisan tenants:run chronovault:prune-personal-data   (alle Mandanten)
 *   Scheduler: täglich 01:00 (routes/console.php)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AuctionLotStatus;
use App\Enums\PriceProposalStatus;
use App\Models\AuctionBid;
use App\Models\PriceProposal;
use Illuminate\Console\Command;

class PrunePersonalDataCommand extends Command
{
    /** Tage, nach denen Gebots-IPs anonymisiert werden. */
    public const IP_RETENTION_DAYS = 30;

    /** Tage, nach denen Gebote geschlossener Lose komplett gelöscht werden. */
    public const BID_RETENTION_DAYS = 180;

    /** Tage nach Abschluss, nach denen Preisvorschläge endgültig gelöscht werden. */
    public const PROPOSAL_RETENTION_DAYS = 90;

    protected $signature = 'chronovault:prune-personal-data';

    protected $description = 'DSGVO-Löschkonzept: anonymisiert Gebots-IPs und löscht abgelaufene Gebote/Preisvorschläge (im Tenant-Kontext ausführen)';

    public function handle(): int
    {
        // 1. IP-Anonymisierung: nach IP_RETENTION_DAYS ist der
        //    Missbrauchs-Zweck erfüllt — die IP wird entfernt, das Gebot
        //    selbst (noch) behalten.
        $anonymized = AuctionBid::query()
            ->whereNotNull('ip_address')
            ->where('created_at', '<', now()->subDays(self::IP_RETENTION_DAYS))
            ->update(['ip_address' => null]);

        // 2. Gebote geschlossener Lose nach BID_RETENTION_DAYS löschen.
        //    Offene Lose bleiben unangetastet (laufende Auktion braucht
        //    ihre Gebotshistorie).
        $deletedBids = AuctionBid::query()
            ->where('created_at', '<', now()->subDays(self::BID_RETENTION_DAYS))
            ->whereHas('lot', fn ($query) => $query->where('status', '!=', AuctionLotStatus::Open->value))
            ->delete();

        // 3. Abgeschlossene Preisvorschläge (accepted/declined) nach
        //    PROPOSAL_RETENTION_DAYS endgültig entfernen — inklusive
        //    bereits soft-deleteter Datensätze (forceDelete löst die
        //    Löschzusage aus dem Formular technisch ein).
        $expiredProposals = PriceProposal::withTrashed()
            ->whereIn('status', [PriceProposalStatus::Accepted->value, PriceProposalStatus::Declined->value])
            ->where('updated_at', '<', now()->subDays(self::PROPOSAL_RETENTION_DAYS))
            ->get();

        foreach ($expiredProposals as $proposal) {
            $proposal->forceDelete();
        }

        $this->info(sprintf(
            'DSGVO-Prune: %d Gebots-IPs anonymisiert, %d Gebote gelöscht, %d Preisvorschläge endgültig entfernt.',
            $anonymized,
            $deletedBids,
            $expiredProposals->count(),
        ));

        return self::SUCCESS;
    }
}
