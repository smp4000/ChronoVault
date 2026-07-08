<?php

/**
 * =========================================================================
 * TransactionType — Art eines Kauf-/Verkaufsvorgangs
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

enum TransactionType: string implements HasColor, HasIcon, HasLabel
{
    case Purchase = 'purchase';
    case Sale = 'sale';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Ankauf',
            self::Sale => 'Verkauf',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase => 'info',
            self::Sale => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Purchase => 'heroicon-m-arrow-down-tray',
            self::Sale => 'heroicon-m-banknotes',
        };
    }
}
