<?php

/**
 * =========================================================================
 * WatchCondition — Erhaltungszustand einer Uhr
 * =========================================================================
 *
 * Zweck:
 *   Typsicherer Zustand für Uhren (branchenübliche Abstufung im
 *   Luxusuhren-Handel). Filament-Contracts für automatische deutsche
 *   Labels/Farben in Tabellen und Formularen.
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - New      : Neu (ungetragen, vom Konzessionär, mit Stempel)
 *   - Unworn   : Ungetragen (wie neu, aber Zweitmarkt)
 *   - VeryGood : Sehr gut (minimale Tragespuren)
 *   - Good     : Gut (normale Tragespuren)
 *   - Fair     : Getragen (deutliche Tragespuren)
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WatchCondition: string implements HasColor, HasLabel
{
    case New = 'new';
    case Unworn = 'unworn';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Fair = 'fair';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Unworn => 'Ungetragen',
            self::VeryGood => 'Sehr gut',
            self::Good => 'Gut',
            self::Fair => 'Getragen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Unworn => 'info',
            self::VeryGood => 'primary',
            self::Good => 'warning',
            self::Fair => 'gray',
        };
    }
}
