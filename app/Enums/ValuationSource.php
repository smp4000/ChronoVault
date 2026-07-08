<?php

/**
 * =========================================================================
 * ValuationSource — Herkunft einer Marktwert-Bewertung
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ValuationSource: string implements HasColor, HasLabel
{
    case Manual = 'manual';
    case AiResearch = 'ai_research';
    case External = 'external';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manuelle Einschätzung',
            self::AiResearch => 'KI-Marktrecherche',
            self::External => 'Externe Quelle',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::AiResearch => 'primary',
            self::External => 'info',
        };
    }
}
