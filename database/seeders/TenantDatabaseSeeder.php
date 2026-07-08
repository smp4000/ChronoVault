<?php

/**
 * =========================================================================
 * TenantDatabaseSeeder — Grundausstattung jeder neuen Tenant-Datenbank
 * =========================================================================
 *
 * Zweck:
 *   Wird beim Provisioning eines Mandanten automatisch ausgeführt
 *   (SeedDatabase-Job in der TenantCreated-Pipeline, konfiguriert in
 *   config/tenancy.php → seeder_parameters). Legt die Standardrollen
 *   und Basis-Berechtigungen an.
 *
 * Verantwortlichkeiten:
 *   - Alle UserRole-Enum-Rollen anlegen (idempotent via firstOrCreate)
 *   - Basis-Berechtigungen definieren und den Rollen zuordnen
 *
 * WARUM Berechtigungen pro Tenant-DB:
 *   Jeder Betrieb kann später eigene Rollen/Rechte pflegen, ohne andere
 *   Mandanten zu beeinflussen — echte Datenisolation (ADR-007).
 *
 * Mögliche Erweiterungen:
 *   - Weitere Berechtigungen je neuem Modul (watches.*, auctions.* …)
 *     werden HIER ergänzt und per tenants:seed nachgerollt.
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Basis-Berechtigungen der Plattform.
     *
     * Namensschema: "<bereich>.<aktion>" — konsistent für alle Module.
     * Neue Module ergänzen ihre Berechtigungen in diesem Array.
     *
     * @var array<string, array<int, UserRole>> Berechtigung => Rollen, die sie erhalten
     */
    private const PERMISSIONS = [
        'users.view' => [UserRole::Owner, UserRole::Admin],
        'users.create' => [UserRole::Owner, UserRole::Admin],
        'users.update' => [UserRole::Owner, UserRole::Admin],
        'users.delete' => [UserRole::Owner, UserRole::Admin],
        'roles.manage' => [UserRole::Owner],
        'settings.manage' => [UserRole::Owner, UserRole::Admin],

        // Modul 2 — Stammdaten (Marken & Kaliber): Lesen dürfen alle,
        // Pflegen ist operatives Arbeiten (inkl. Mitarbeiter), Löschen
        // bleibt der Verwaltung vorbehalten.
        'master_data.view' => [UserRole::Owner, UserRole::Admin, UserRole::Employee, UserRole::Viewer],
        'master_data.create' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'master_data.update' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'master_data.delete' => [UserRole::Owner, UserRole::Admin],

        // Modul 3 — Uhrenbestand: gleiche Semantik wie Stammdaten
        // (Pflege ist operatives Arbeiten, Löschen bleibt der Verwaltung).
        'watches.view' => [UserRole::Owner, UserRole::Admin, UserRole::Employee, UserRole::Viewer],
        'watches.create' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'watches.update' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'watches.delete' => [UserRole::Owner, UserRole::Admin],

        // Modul 5 — Kundenstamm & Kauf-/Verkaufsbelege: Verkaufen ist
        // operatives Tagesgeschäft; Belege löschen (Storno) nur Verwaltung.
        'contacts.view' => [UserRole::Owner, UserRole::Admin, UserRole::Employee, UserRole::Viewer],
        'contacts.create' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'contacts.update' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'contacts.delete' => [UserRole::Owner, UserRole::Admin],
        'transactions.view' => [UserRole::Owner, UserRole::Admin, UserRole::Employee, UserRole::Viewer],
        'transactions.create' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'transactions.update' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'transactions.delete' => [UserRole::Owner, UserRole::Admin],

        // Modul 6 — Service-Historie & Wartung: gleiche Semantik.
        'services.view' => [UserRole::Owner, UserRole::Admin, UserRole::Employee, UserRole::Viewer],
        'services.create' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'services.update' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'services.delete' => [UserRole::Owner, UserRole::Admin],

        // Modul 7 — Bewertungen & Marktwert: gleiche Semantik.
        'valuations.view' => [UserRole::Owner, UserRole::Admin, UserRole::Employee, UserRole::Viewer],
        'valuations.create' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'valuations.update' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'valuations.delete' => [UserRole::Owner, UserRole::Admin],

        // Modul 8 — Auktionen & Lose: gleiche Semantik (Einliefern und
        // Zuschlagen ist operatives Arbeiten, Löschen nur Verwaltung).
        'auctions.view' => [UserRole::Owner, UserRole::Admin, UserRole::Employee, UserRole::Viewer],
        'auctions.create' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'auctions.update' => [UserRole::Owner, UserRole::Admin, UserRole::Employee],
        'auctions.delete' => [UserRole::Owner, UserRole::Admin],
    ];

    public function run(): void
    {
        // 1. Alle Standardrollen anlegen (idempotent — der Seeder darf
        //    beliebig oft laufen, z. B. bei Nach-Seeds via tenants:seed).
        $roles = [];

        foreach (UserRole::cases() as $role) {
            $roles[$role->value] = Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }

        // 2. Berechtigungen anlegen und zuordnen.
        //    syncPermissions wird bewusst NICHT genutzt, damit individuell
        //    ergänzte Rechte eines Mandanten erhalten bleiben.
        foreach (self::PERMISSIONS as $permissionName => $allowedRoles) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            foreach ($allowedRoles as $role) {
                $roles[$role->value]->givePermissionTo($permission);
            }
        }

        // 3. Owner erhält pauschal ALLE Berechtigungen — auch zukünftige.
        $roles[UserRole::Owner->value]->givePermissionTo(Permission::all());

        // 4. Stammdaten-Grundausstattung (Marken & Kaliber) — idempotent.
        $this->call(MasterDataSeeder::class);
    }
}
