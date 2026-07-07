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
    }
}
