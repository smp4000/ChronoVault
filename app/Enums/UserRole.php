<?php

/**
 * =========================================================================
 * UserRole — Standard-Rollen innerhalb einer Tenant-Datenbank
 * =========================================================================
 *
 * Zweck:
 *   Definiert die vier Standard-Rollen, die jeder neue Mandant beim
 *   Provisioning geseedet bekommt (spatie/laravel-permission). Das Enum
 *   ist die EINZIGE Quelle für Rollennamen — nirgendwo im Code stehen
 *   Rollen-Strings hart verdrahtet (DRY, Tippfehler-Schutz).
 *
 * Rollen (Code Englisch, UI Deutsch):
 *   - Owner    : Inhaber — Vollzugriff inkl. Benutzerverwaltung & Abrechnung
 *   - Admin    : Administrator — Vollzugriff auf Fachdaten & Benutzer
 *   - Employee : Mitarbeiter — operatives Arbeiten (Uhren, Kunden)
 *   - Viewer   : Betrachter — Nur-Lese-Zugriff (z. B. Steuerberater)
 *
 * WARUM Enum statt DB-only:
 *   Die Rollen selbst liegen (pro Tenant) in der Datenbank; das Enum
 *   liefert typsichere Referenzen darauf. Mandanten können später eigene
 *   Zusatzrollen anlegen — die Standardrollen bleiben geschützt.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Employee = 'employee';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Inhaber',
            self::Admin => 'Administrator',
            self::Employee => 'Mitarbeiter',
            self::Viewer => 'Betrachter',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owner => 'warning',
            self::Admin => 'danger',
            self::Employee => 'info',
            self::Viewer => 'gray',
        };
    }

    /**
     * Rollen, die Benutzer & Einstellungen des Mandanten verwalten dürfen.
     * Zentrale Definition — Policies greifen hierauf zu statt auf Strings.
     *
     * @return array<self>
     */
    public static function managementRoles(): array
    {
        return [self::Owner, self::Admin];
    }
}
