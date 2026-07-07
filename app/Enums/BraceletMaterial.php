<?php

/**
 * =========================================================================
 * BraceletMaterial — Armbandmaterial (Chrono24-Katalog)
 * =========================================================================
 *
 * Zweck:
 *   Standardisierte Armband-Materialliste nach Chrono24-Vorbild.
 *   Eigenes Enum (statt CaseMaterial), weil Bänder zusätzliche
 *   Materialien haben (Leder, Kautschuk, Textil …).
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BraceletMaterial: string implements HasLabel
{
    case Steel = 'steel';
    case Leather = 'leather';
    case CrocodileLeather = 'crocodile_leather';
    case CalfLeather = 'calf_leather';
    case Rubber = 'rubber';
    case Silicone = 'silicone';
    case Textile = 'textile';
    case GoldSteel = 'gold_steel';
    case YellowGold = 'yellow_gold';
    case RoseGold = 'rose_gold';
    case WhiteGold = 'white_gold';
    case GoldPlated = 'gold_plated';
    case Titanium = 'titanium';
    case Ceramic = 'ceramic';
    case Platinum = 'platinum';
    case Silver = 'silver';
    case Aluminum = 'aluminum';
    case Plastic = 'plastic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Steel => 'Stahl',
            self::Leather => 'Leder',
            self::CrocodileLeather => 'Krokodilleder',
            self::CalfLeather => 'Kalbsleder',
            self::Rubber => 'Kautschuk',
            self::Silicone => 'Silikon',
            self::Textile => 'Textil',
            self::GoldSteel => 'Gold/Stahl',
            self::YellowGold => 'Gelbgold',
            self::RoseGold => 'Roségold',
            self::WhiteGold => 'Weißgold',
            self::GoldPlated => 'Vergoldet',
            self::Titanium => 'Titan',
            self::Ceramic => 'Keramik',
            self::Platinum => 'Platin',
            self::Silver => 'Silber',
            self::Aluminum => 'Aluminium',
            self::Plastic => 'Kunststoff',
        };
    }
}
