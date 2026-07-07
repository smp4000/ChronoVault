<?php

/**
 * =========================================================================
 * GlassType — Uhrenglas (Chrono24: "Glas")
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GlassType: string implements HasLabel
{
    case Sapphire = 'sapphire';
    case Mineral = 'mineral';
    case Plexiglass = 'plexiglass';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sapphire => 'Saphirglas',
            self::Mineral => 'Mineralglas',
            self::Plexiglass => 'Plexiglas/Kunststoff',
        };
    }
}
