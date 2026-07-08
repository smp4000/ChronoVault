<?php

/**
 * =========================================================================
 * AuctionVenue — Austragungsform einer Auktion (Modul 8)
 * =========================================================================
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - Saleroom : Saalauktion (klassisch vor Ort)
 *   - Online   : Online-Auktion
 *   - Hybrid   : Hybrid (Saal + Online-Gebote)
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AuctionVenue: string implements HasIcon, HasLabel
{
    case Saleroom = 'saleroom';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Saleroom => 'Saalauktion',
            self::Online => 'Online-Auktion',
            self::Hybrid => 'Hybrid (Saal + Online)',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Saleroom => 'heroicon-m-building-library',
            self::Online => 'heroicon-m-globe-alt',
            self::Hybrid => 'heroicon-m-arrows-right-left',
        };
    }
}
