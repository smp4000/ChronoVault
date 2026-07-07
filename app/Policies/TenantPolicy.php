<?php

/**
 * =========================================================================
 * TenantPolicy — Autorisierung für die Mandantenverwaltung (zentrales Panel)
 * =========================================================================
 *
 * Zweck:
 *   Regelt, wer im zentralen Admin-Panel Mandanten sehen/anlegen/ändern/
 *   löschen darf. Aktuell sind ALLE zentralen Benutzer Plattform-
 *   Administratoren — die Policy existiert trotzdem von Anfang an, damit
 *   Filament sie automatisch nutzt und spätere Verfeinerungen (z. B.
 *   Support-Rolle mit Nur-Lese-Zugriff) nur HIER stattfinden.
 *
 * WARUM forceDelete separat:
 *   Die endgültige Löschung vernichtet eine komplette Kunden-Datenbank.
 *   Sie bleibt auch autorisierungsseitig ein eigener, bewusster Schritt.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Grundregel: Nur im zentralen Kontext (kein aktiver Tenant) erlaubt.
     * Tenant-Benutzer dürfen NIEMALS andere Mandanten verwalten.
     */
    private function isCentralContext(): bool
    {
        return ! tenancy()->initialized;
    }

    public function viewAny(User $user): bool
    {
        return $this->isCentralContext();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->isCentralContext();
    }

    public function create(User $user): bool
    {
        return $this->isCentralContext();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->isCentralContext();
    }

    /** Archivieren (Soft Delete) — reversibel. */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->isCentralContext();
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $this->isCentralContext();
    }

    /** Endgültige Löschung inkl. Tenant-Datenbank — nur archivierte Tenants. */
    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $this->isCentralContext() && $tenant->trashed();
    }
}
