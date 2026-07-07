<?php

/**
 * =========================================================================
 * WatchColor — Farbwerte für Zifferblatt, Lünette und Armband
 * =========================================================================
 *
 * Zweck:
 *   Standardisierte Farbliste nach Chrono24-Vorbild statt Freitext.
 *   Ein gemeinsamer Wertevorrat für Zifferblatt-, Lünetten- und
 *   Armbandfarbe (die Chrono24-Listen überschneiden sich fast komplett).
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WatchColor: string implements HasLabel
{
    case Black = 'black';
    case White = 'white';
    case Silver = 'silver';
    case Grey = 'grey';
    case Blue = 'blue';
    case Green = 'green';
    case Red = 'red';
    case Bordeaux = 'bordeaux';
    case Brown = 'brown';
    case Beige = 'beige';
    case Champagne = 'champagne';
    case Gold = 'gold';
    case Bronze = 'bronze';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Pink = 'pink';
    case Purple = 'purple';
    case MotherOfPearl = 'mother_of_pearl';
    case Skeletonized = 'skeletonized';
    case Transparent = 'transparent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Black => 'Schwarz',
            self::White => 'Weiß',
            self::Silver => 'Silber',
            self::Grey => 'Grau',
            self::Blue => 'Blau',
            self::Green => 'Grün',
            self::Red => 'Rot',
            self::Bordeaux => 'Bordeaux',
            self::Brown => 'Braun',
            self::Beige => 'Beige',
            self::Champagne => 'Champagner',
            self::Gold => 'Gold',
            self::Bronze => 'Bronze',
            self::Orange => 'Orange',
            self::Yellow => 'Gelb',
            self::Pink => 'Rosa',
            self::Purple => 'Violett',
            self::MotherOfPearl => 'Perlmutt',
            self::Skeletonized => 'Skelettiert',
            self::Transparent => 'Transparent',
        };
    }
}
