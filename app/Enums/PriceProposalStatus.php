<?php

/**
 * =========================================================================
 * PriceProposalStatus — Bearbeitungsstatus eines Preisvorschlags (Shop)
 * =========================================================================
 *
 * Zweck:
 *   Typsicherer Status mit Filament-Contracts für deutsche Labels,
 *   Farben und Icons in der Preisvorschläge-Tabelle.
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - New       : Neu (unbearbeitet)
 *   - Countered : Gegenangebot unterbreitet (wartet auf den Kunden)
 *   - Accepted  : Angenommen (Zuschlag: Verkauf + Rechnung + Mail)
 *   - Declined  : Abgelehnt
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PriceProposalStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Countered = 'countered';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Countered => 'Gegenangebot',
            self::Accepted => 'Angenommen',
            self::Declined => 'Abgelehnt',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Countered => 'info',
            self::Accepted => 'success',
            self::Declined => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::New => 'heroicon-m-sparkles',
            self::Countered => 'heroicon-m-arrows-right-left',
            self::Accepted => 'heroicon-m-check-circle',
            self::Declined => 'heroicon-m-x-circle',
        };
    }

    /**
     * Offen = der Händler kann noch reagieren (annehmen/ablehnen/kontern).
     */
    public function isOpen(): bool
    {
        return $this === self::New || $this === self::Countered;
    }
}
