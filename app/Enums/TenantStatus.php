<?php

/**
 * =========================================================================
 * TenantStatus — Lebenszyklus-Status eines Mandanten
 * =========================================================================
 *
 * Zweck:
 *   Typsicherer Status für Tenants statt loser Strings. Implementiert die
 *   Filament-Interfaces HasLabel/HasColor/HasIcon, damit der Status in
 *   Tabellen, Formularen und Infolists automatisch korrekt (und auf
 *   Deutsch) gerendert wird — ohne UI-Logik in den Resources.
 *
 * Werte:
 *   - Trial     : Testphase nach Registrierung
 *   - Active    : Voll aktiver, zahlender Mandant
 *   - Suspended : Gesperrt (z. B. Zahlungsverzug) — Login wird verweigert
 *   - Archived  : Soft-deleted / stillgelegt, Daten bleiben erhalten
 *
 * Mögliche Erweiterungen:
 *   - PendingSetup (Onboarding-Wizard noch nicht abgeschlossen)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TenantStatus: string implements HasColor, HasIcon, HasLabel
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    /**
     * Deutsches UI-Label (Quellcode Englisch, UI Deutsch — Projektregel).
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Trial => 'Testphase',
            self::Active => 'Aktiv',
            self::Suspended => 'Gesperrt',
            self::Archived => 'Archiviert',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Archived => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Trial => 'heroicon-m-beaker',
            self::Active => 'heroicon-m-check-circle',
            self::Suspended => 'heroicon-m-no-symbol',
            self::Archived => 'heroicon-m-archive-box',
        };
    }
}
