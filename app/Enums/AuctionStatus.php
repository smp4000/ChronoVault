<?php

/**
 * =========================================================================
 * AuctionStatus — Lebenszyklus einer Auktion (Modul 8)
 * =========================================================================
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - Draft     : Entwurf (Lose werden zusammengestellt)
 *   - Scheduled : Geplant (Termin steht, Katalog fertig)
 *   - Live      : Läuft (Versteigerung im Gange)
 *   - Completed : Abgeschlossen (alle Lose abgerechnet)
 *   - Cancelled : Abgesagt
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AuctionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Scheduled => 'Geplant',
            self::Live => 'Läuft',
            self::Completed => 'Abgeschlossen',
            self::Cancelled => 'Abgesagt',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Live => 'success',
            self::Completed => 'primary',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-m-pencil-square',
            self::Scheduled => 'heroicon-m-calendar-days',
            self::Live => 'heroicon-m-megaphone',
            self::Completed => 'heroicon-m-check-circle',
            self::Cancelled => 'heroicon-m-x-circle',
        };
    }

    /**
     * Status, in denen Lose eingeliefert werden dürfen (vor dem Zuschlag).
     *
     * @return array<self>
     */
    public static function acceptingLots(): array
    {
        return [self::Draft, self::Scheduled, self::Live];
    }
}
