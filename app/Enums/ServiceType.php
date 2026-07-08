<?php

/**
 * =========================================================================
 * ServiceType — Art eines Servicevorgangs
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceType: string implements HasLabel
{
    case FullService = 'full_service';
    case Repair = 'repair';
    case Polishing = 'polishing';
    case BatteryChange = 'battery_change';
    case WaterResistanceTest = 'water_resistance_test';
    case Regulation = 'regulation';
    case BraceletService = 'bracelet_service';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::FullService => 'Revision (Komplettservice)',
            self::Repair => 'Reparatur',
            self::Polishing => 'Aufarbeitung/Politur',
            self::BatteryChange => 'Batteriewechsel',
            self::WaterResistanceTest => 'Wasserdichtigkeitsprüfung',
            self::Regulation => 'Regulierung',
            self::BraceletService => 'Band-Service',
            self::Other => 'Sonstiges',
        };
    }
}
