<?php

/**
 * =========================================================================
 * WatchStatus — Bestandsstatus einer Uhr (Lebenszyklus im Betrieb)
 * =========================================================================
 *
 * Zweck:
 *   Typsicherer Bestandsstatus. Filament-Contracts für automatische
 *   deutsche Labels/Farben/Icons in Tabellen, Filtern und Widgets.
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - InStock     : An Lager (verkaufsbereit)
 *   - Reserved    : Reserviert (Kunde hat zugesagt, noch nicht bezahlt)
 *   - InService   : Im Service (Revision/Reparatur, nicht verfügbar)
 *   - Consignment : Kommission (Fremdeigentum im Verkauf)
 *   - InAuction   : In Auktion (als Los eingeliefert — Modul 8)
 *   - Sold        : Verkauft (bleibt für Historie/Statistik erhalten)
 *   - Wishlist    : Wunschliste (NICHT im Besitz — beobachtetes
 *                   Wunschmodell mit Zielpreis; nächtliche Bewertung
 *                   läuft mit, Alarm-Mail bei Ziel-Erreichen)
 *   - PrivateCollection : Eigentum (private Sammlung — nicht zum
 *                   Verkauf, aber versichert: zählt in Versicherungs-
 *                   liste und Bestandswert, nie im Shop)
 *
 * Mögliche Erweiterungen:
 *   - OnApproval (Ansichtssendung)
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WatchStatus: string implements HasColor, HasIcon, HasLabel
{
    case InStock = 'in_stock';
    case Reserved = 'reserved';
    case InService = 'in_service';
    case Consignment = 'consignment';
    case InAuction = 'in_auction';
    case Sold = 'sold';
    case Wishlist = 'wishlist';
    case PrivateCollection = 'private_collection';

    public function getLabel(): string
    {
        return match ($this) {
            self::InStock => 'An Lager',
            self::Reserved => 'Reserviert',
            self::InService => 'Im Service',
            self::Consignment => 'Kommission',
            self::InAuction => 'In Auktion',
            self::Sold => 'Verkauft',
            self::Wishlist => 'Wunschliste',
            self::PrivateCollection => 'Eigentum (Sammlung)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::Reserved => 'warning',
            self::InService => 'info',
            self::Consignment => 'primary',
            self::InAuction => 'warning',
            self::Sold => 'gray',
            self::Wishlist => 'danger',
            self::PrivateCollection => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::InStock => 'heroicon-m-check-circle',
            self::Reserved => 'heroicon-m-clock',
            self::InService => 'heroicon-m-wrench-screwdriver',
            self::Consignment => 'heroicon-m-arrows-right-left',
            self::InAuction => 'heroicon-m-megaphone',
            self::Sold => 'heroicon-m-banknotes',
            self::Wishlist => 'heroicon-m-heart',
            self::PrivateCollection => 'heroicon-m-lock-closed',
        };
    }

    /**
     * Status, in denen die Uhr verkäuflich ist (Lager-Kennzahlen,
     * spätere Verkaufslogik in Modul 5).
     *
     * @return array<self>
     */
    public static function sellableStatuses(): array
    {
        return [self::InStock, self::Consignment];
    }

    /**
     * Status, in denen eine VERÖFFENTLICHTE Uhr im Shop kaufbar ist.
     * Eigentum (Sammlung) ist bewusst dabei: Standardmäßig bleibt die
     * Sammlung privat (Statuswechsel entfernt die Veröffentlichung im
     * WatchObserver) — veröffentlicht der Sammler eine Eigentums-Uhr
     * aber gezielt, ist sie im Shop sichtbar und kaufbar.
     *
     * @return array<self>
     */
    public static function shopSellableStatuses(): array
    {
        return [self::InStock, self::Consignment, self::PrivateCollection];
    }
}
