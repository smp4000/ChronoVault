<?php

/**
 * =========================================================================
 * MovementType — Werktyp eines Kalibers (Uhrwerk-Antriebsart)
 * =========================================================================
 *
 * Zweck:
 *   Typsicherer Werktyp für Kaliber statt loser Strings. Implementiert
 *   die Filament-Interfaces HasLabel/HasColor/HasIcon, damit der Typ in
 *   Tabellen und Formularen automatisch korrekt (und auf Deutsch)
 *   gerendert wird — ohne UI-Logik in den Resources.
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - Manual      : Handaufzug
 *   - Automatic   : Automatik (Rotor-Selbstaufzug)
 *   - Quartz      : Quarz (batteriebetrieben)
 *   - Solar       : Solar (lichtbetriebener Quarz)
 *   - SpringDrive : Spring Drive (Seiko-Hybridtechnik, Eigenname)
 *
 * Mögliche Erweiterungen:
 *   - Kinetic (Seiko), Mecaquartz — bei Bedarf ergänzen; persistierte
 *     Werte NIE umbenennen (liegen in Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasColor, HasIcon, HasLabel
{
    case Manual = 'manual';
    case Automatic = 'automatic';
    case Quartz = 'quartz';
    case Solar = 'solar';
    case SpringDrive = 'spring_drive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Handaufzug',
            self::Automatic => 'Automatik',
            self::Quartz => 'Quarz',
            self::Solar => 'Solar',
            self::SpringDrive => 'Spring Drive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'warning',
            self::Automatic => 'success',
            self::Quartz => 'info',
            self::Solar => 'primary',
            self::SpringDrive => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Manual => 'heroicon-m-hand-raised',
            self::Automatic => 'heroicon-m-arrow-path',
            self::Quartz => 'heroicon-m-bolt',
            self::Solar => 'heroicon-m-sun',
            self::SpringDrive => 'heroicon-m-cog-6-tooth',
        };
    }
}
