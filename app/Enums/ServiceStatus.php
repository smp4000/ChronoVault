<?php

/**
 * =========================================================================
 * ServiceStatus — Bearbeitungsstand eines Servicevorgangs
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ServiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::InProgress => 'In Arbeit',
            self::Completed => 'Abgeschlossen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Completed => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-m-clock',
            self::InProgress => 'heroicon-m-wrench-screwdriver',
            self::Completed => 'heroicon-m-check-circle',
        };
    }
}
