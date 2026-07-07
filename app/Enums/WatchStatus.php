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
 *   - Sold        : Verkauft (bleibt für Historie/Statistik erhalten)
 *
 * Mögliche Erweiterungen:
 *   - InAuction (Modul 8), OnApproval (Ansichtssendung)
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
    case Sold = 'sold';

    public function getLabel(): string
    {
        return match ($this) {
            self::InStock => 'An Lager',
            self::Reserved => 'Reserviert',
            self::InService => 'Im Service',
            self::Consignment => 'Kommission',
            self::Sold => 'Verkauft',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::Reserved => 'warning',
            self::InService => 'info',
            self::Consignment => 'primary',
            self::Sold => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::InStock => 'heroicon-m-check-circle',
            self::Reserved => 'heroicon-m-clock',
            self::InService => 'heroicon-m-wrench-screwdriver',
            self::Consignment => 'heroicon-m-arrows-right-left',
            self::Sold => 'heroicon-m-banknotes',
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
}
