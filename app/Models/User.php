<?php

/**
 * =========================================================================
 * User — Benutzer-Model (zentrale UND Tenant-Datenbanken)
 * =========================================================================
 *
 * Zweck:
 *   Ein einziges User-Model für beide Kontexte:
 *   - ZENTRAL: Plattform-Betreiber (Super-Admins) in der zentralen DB
 *   - TENANT : Mitarbeiter eines Mandanten in dessen eigener DB
 *
 *   WARUM ein Model für beide? stancl/tenancy wechselt die Default-
 *   Datenbankverbindung pro Request. Dasselbe Model liest also automatisch
 *   aus der richtigen users-Tabelle — zwei Model-Klassen wären Duplikation
 *   ohne Mehrwert.
 *
 * Verantwortlichkeiten:
 *   - Authentifizierung (Laravel Auth / Filament Login)
 *   - Panel-Zugriffskontrolle (FilamentUser::canAccessPanel)
 *   - Rollen & Berechtigungen (spatie HasRoles — Tabellen existieren nur
 *     in Tenant-DBs; im zentralen Kontext dürfen Rollen-Methoden daher
 *     nicht aufgerufen werden)
 *
 * Abhängigkeiten:
 *   - filament/filament (FilamentUser-Contract)
 *   - spatie/laravel-permission (HasRoles)
 *
 * Mögliche Erweiterungen:
 *   - Avatar via spatie/laravel-medialibrary (Modul 4)
 *   - Zwei-Faktor-Authentifizierung
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles {
        // Original-Methode unter neuem Namen verfügbar halten — die
        // Überschreibung unten ergänzt nur den Tenant-Kontext-Guard.
        checkPermissionTo as protected spatieCheckPermissionTo;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Panel-Zugriffskontrolle (Filament).
     *
     * Sicherheitslogik:
     * - "admin"-Panel (zentrale Plattformverwaltung): nur erreichbar, wenn
     *   KEIN Tenant-Kontext aktiv ist — also nur für zentrale Benutzer.
     * - "app"-Panel (Mandanten-Anwendung): nur im Tenant-Kontext UND nur,
     *   wenn der Mandant aktiv ist (nicht gesperrt/archiviert).
     *
     * WARUM hier statt Middleware: Filament ruft diese Methode bei Login
     * UND bei jedem Panel-Zugriff auf — ein zentraler, nicht umgehbarer Punkt.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return ! tenancy()->initialized;
        }

        if ($panel->getId() === 'app') {
            /** @var Tenant|null $tenant */
            $tenant = tenant();

            return $tenant !== null && $tenant->isActive();
        }

        return false;
    }

    /**
     * Berechtigungsprüfung nur im Tenant-Kontext.
     *
     * spatie/laravel-permission registriert einen globalen Gate::before-
     * Hook, der diese Methode bei JEDEM Gate-Check aufruft — auch im
     * zentralen Admin-Panel. Die permission-Tabellen existieren aber nur
     * in Tenant-Datenbanken; ohne diesen Guard crasht jeder zentrale
     * Policy-Check mit "Table 'chronovault.permissions' doesn't exist".
     *
     * Zentrale Benutzer haben keine spatie-Berechtigungen (false) — der
     * Gate::before-Hook wertet false als "keine Aussage" und fällt auf
     * die Policies zurück (TenantPolicy etc.), genau wie beabsichtigt.
     *
     * @param  \BackedEnum|string|int|Permission  $permission
     * @param  string|null  $guardName
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        if (! tenancy()->initialized) {
            return false;
        }

        return $this->spatieCheckPermissionTo($permission, $guardName);
    }
}
