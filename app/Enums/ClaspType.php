<?php

/**
 * =========================================================================
 * ClaspType — Schließentyp des Armbands (Chrono24: "Schließe")
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClaspType: string implements HasLabel
{
    case PinBuckle = 'pin_buckle';
    case FoldingClasp = 'folding_clasp';
    case DoubleFoldingClasp = 'double_folding_clasp';
    case JewelryClasp = 'jewelry_clasp';
    case NoClasp = 'no_clasp';

    public function getLabel(): string
    {
        return match ($this) {
            self::PinBuckle => 'Dornschließe',
            self::FoldingClasp => 'Faltschließe',
            self::DoubleFoldingClasp => 'Doppelfaltschließe',
            self::JewelryClasp => 'Schmuckbandschließe',
            self::NoClasp => 'Ohne Schließe',
        };
    }
}
