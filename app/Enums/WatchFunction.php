<?php

/**
 * =========================================================================
 * WatchFunction — Funktionen/Komplikationen einer Uhr (Chrono24-Katalog)
 * =========================================================================
 *
 * Zweck:
 *   Standardisierte Funktionsliste (Mehrfachauswahl, als JSON-Array in
 *   watches.functions persistiert). Grundlage für Filter und den
 *   späteren Inserat-Export.
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WatchFunction: string implements HasLabel
{
    case Date = 'date';
    case DayDate = 'day_date';
    case Chronograph = 'chronograph';
    case Flyback = 'flyback';
    case Gmt = 'gmt';
    case WorldTime = 'world_time';
    case Moonphase = 'moonphase';
    case AnnualCalendar = 'annual_calendar';
    case PerpetualCalendar = 'perpetual_calendar';
    case Tourbillon = 'tourbillon';
    case SmallSeconds = 'small_seconds';
    case PowerReserveDisplay = 'power_reserve_display';
    case Alarm = 'alarm';
    case MinuteRepeater = 'minute_repeater';
    case Tachymeter = 'tachymeter';

    public function getLabel(): string
    {
        return match ($this) {
            self::Date => 'Datum',
            self::DayDate => 'Wochentag',
            self::Chronograph => 'Chronograph',
            self::Flyback => 'Flyback',
            self::Gmt => 'GMT/2. Zeitzone',
            self::WorldTime => 'Weltzeit',
            self::Moonphase => 'Mondphase',
            self::AnnualCalendar => 'Jahreskalender',
            self::PerpetualCalendar => 'Ewiger Kalender',
            self::Tourbillon => 'Tourbillon',
            self::SmallSeconds => 'Kleine Sekunde',
            self::PowerReserveDisplay => 'Gangreserveanzeige',
            self::Alarm => 'Wecker',
            self::MinuteRepeater => 'Minutenrepetition',
            self::Tachymeter => 'Tachymeter',
        };
    }
}
