<?php

/**
 * =========================================================================
 * ContactType — Art eines Kontakts im Kundenstamm
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactType: string implements HasColor, HasLabel
{
    case PrivatePerson = 'private';
    case Dealer = 'dealer';
    case AuctionHouse = 'auction_house';
    case Workshop = 'workshop';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PrivatePerson => 'Privatperson',
            self::Dealer => 'Händler',
            self::AuctionHouse => 'Auktionshaus',
            self::Workshop => 'Werkstatt/Service',
            self::Other => 'Sonstige',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PrivatePerson => 'info',
            self::Dealer => 'primary',
            self::AuctionHouse => 'warning',
            self::Workshop => 'success',
            self::Other => 'gray',
        };
    }
}
