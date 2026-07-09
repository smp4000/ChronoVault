<?php

/**
 * =========================================================================
 * BezelType — Lünettentyp (Chrono24-/Hersteller-Attribut)
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BezelType: string implements HasLabel
{
    case Fixed = 'fixed';
    case Unidirectional = 'unidirectional';
    case Bidirectional = 'bidirectional';
    case InnerRotating = 'inner_rotating';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Feststehend',
            self::Unidirectional => 'Einseitig drehbar',
            self::Bidirectional => 'Beidseitig drehbar',
            self::InnerRotating => 'Innenliegend drehbar',
        };
    }
}
