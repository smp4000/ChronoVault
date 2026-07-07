<?php

/**
 * =========================================================================
 * WatchGender — Zielgruppe der Uhr (Chrono24: "Geschlecht")
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WatchGender: string implements HasLabel
{
    case Mens = 'mens';
    case Womens = 'womens';
    case Unisex = 'unisex';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mens => 'Herrenuhr',
            self::Womens => 'Damenuhr',
            self::Unisex => 'Unisex',
        };
    }
}
