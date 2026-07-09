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
 *   - New      : Neu (unbearbeitet)
 *   - Accepted : Angenommen (Händler akzeptiert den Vorschlag)
 *   - Declined : Abgelehnt
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
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Accepted => 'Angenommen',
            self::Declined => 'Abgelehnt',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Accepted => 'success',
            self::Declined => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::New => 'heroicon-m-sparkles',
            self::Accepted => 'heroicon-m-check-circle',
            self::Declined => 'heroicon-m-x-circle',
        };
    }
}
