<?php

/**
 * =========================================================================
 * CaseBack — Gehäuseboden (Chrono24-/Hersteller-Attribut)
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CaseBack: string implements HasLabel
{
    case Solid = 'solid';
    case Exhibition = 'exhibition';
    case Engraved = 'engraved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Solid => 'Geschlossen',
            self::Exhibition => 'Glasboden (Sichtboden)',
            self::Engraved => 'Graviert',
        };
    }
}
