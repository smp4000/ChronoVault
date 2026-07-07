<?php

/**
 * =========================================================================
 * DialNumerals — Zifferblatt-Zahlen (Chrono24: "Zifferblatt-Zahlen")
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DialNumerals: string implements HasLabel
{
    case Arabic = 'arabic';
    case Roman = 'roman';
    case Indices = 'indices';
    case NoNumerals = 'no_numerals';

    public function getLabel(): string
    {
        return match ($this) {
            self::Arabic => 'Arabische Ziffern',
            self::Roman => 'Römische Ziffern',
            self::Indices => 'Indizes/Striche',
            self::NoNumerals => 'Keine Ziffern',
        };
    }
}
