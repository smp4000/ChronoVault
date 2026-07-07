<?php

/**
 * =========================================================================
 * CaseMaterial — Gehäuse-/Lünetten-/Schließenmaterial (Chrono24-Katalog)
 * =========================================================================
 *
 * Zweck:
 *   Standardisierte Materialliste nach Chrono24-Vorbild statt Freitext —
 *   Grundlage für saubere Filter, Auswertungen und den späteren
 *   Inserat-Export (Chrono24-Integration). Wird für Gehäuse, Lünette
 *   UND Schließe verwendet (identischer Wertevorrat).
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CaseMaterial: string implements HasLabel
{
    case Steel = 'steel';
    case YellowGold = 'yellow_gold';
    case RoseGold = 'rose_gold';
    case RedGold = 'red_gold';
    case WhiteGold = 'white_gold';
    case GoldSteel = 'gold_steel';
    case GoldPlated = 'gold_plated';
    case Titanium = 'titanium';
    case Ceramic = 'ceramic';
    case Bronze = 'bronze';
    case Carbon = 'carbon';
    case Platinum = 'platinum';
    case Palladium = 'palladium';
    case Tantalum = 'tantalum';
    case Tungsten = 'tungsten';
    case Aluminum = 'aluminum';
    case Silver = 'silver';
    case Brass = 'brass';
    case Plastic = 'plastic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Steel => 'Stahl',
            self::YellowGold => 'Gelbgold',
            self::RoseGold => 'Roségold',
            self::RedGold => 'Rotgold',
            self::WhiteGold => 'Weißgold',
            self::GoldSteel => 'Gold/Stahl',
            self::GoldPlated => 'Vergoldet',
            self::Titanium => 'Titan',
            self::Ceramic => 'Keramik',
            self::Bronze => 'Bronze',
            self::Carbon => 'Carbon',
            self::Platinum => 'Platin',
            self::Palladium => 'Palladium',
            self::Tantalum => 'Tantal',
            self::Tungsten => 'Wolfram',
            self::Aluminum => 'Aluminium',
            self::Silver => 'Silber',
            self::Brass => 'Messing',
            self::Plastic => 'Kunststoff',
        };
    }
}
