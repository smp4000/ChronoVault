<?php

/**
 * =========================================================================
 * WishlistStatus — Status eines Wunschlisten-Eintrags
 * =========================================================================
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - Active    : Beobachtung aktiv (nächtliche Wertermittlung läuft)
 *   - Paused    : Pausiert (bleibt stehen, keine Recherche/Mails)
 *   - Purchased : Gekauft (erledigt — Uhr ist im Bestand)
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WishlistStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Paused = 'paused';
    case Purchased = 'purchased';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Beobachtung aktiv',
            self::Paused => 'Pausiert',
            self::Purchased => 'Gekauft',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'gray',
            self::Purchased => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-m-eye',
            self::Paused => 'heroicon-m-pause-circle',
            self::Purchased => 'heroicon-m-check-badge',
        };
    }
}
