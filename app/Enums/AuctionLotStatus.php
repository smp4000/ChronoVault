<?php

/**
 * =========================================================================
 * AuctionLotStatus — Ergebnis eines Auktionsloses (Modul 8)
 * =========================================================================
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - Open      : Offen (eingeliefert, noch kein Ergebnis)
 *   - Sold      : Zugeschlagen (Verkauf via RecordSaleAction)
 *   - Unsold    : Rückgang (kein Gebot über Limit — Status-Restore)
 *   - Withdrawn : Zurückgezogen (vor dem Aufruf entnommen — Status-Restore)
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AuctionLotStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case Sold = 'sold';
    case Unsold = 'unsold';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Sold => 'Zugeschlagen',
            self::Unsold => 'Rückgang',
            self::Withdrawn => 'Zurückgezogen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Sold => 'success',
            self::Unsold => 'warning',
            self::Withdrawn => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-m-clock',
            self::Sold => 'heroicon-m-check-circle',
            self::Unsold => 'heroicon-m-arrow-uturn-left',
            self::Withdrawn => 'heroicon-m-x-circle',
        };
    }
}
