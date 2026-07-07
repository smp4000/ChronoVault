<?php

/**
 * =========================================================================
 * WatchPolicy — Autorisierung des Uhrenbestands im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Regelt den Zugriff auf Uhren über die geseedeten watches.*-
 *   Berechtigungen (Tenant-DB). KEINE hartkodierten Rollennamen —
 *   Mandanten können eigene Rollen mit diesen Rechten ausstatten.
 *
 * Standard-Zuordnung (TenantDatabaseSeeder):
 *   view → alle Rollen · create/update → Owner/Admin/Employee ·
 *   delete → Owner/Admin
 *
 * Hinweis:
 *   Verkaufte Uhren (Status sold) bleiben bewusst editier- und
 *   löschbar — Korrekturen kommen vor. Die Verkaufslogik selbst
 *   (inkl. Sperren) folgt in Modul 5.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Watch;

class WatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('watches.view');
    }

    public function view(User $user, Watch $watch): bool
    {
        return $user->can('watches.view');
    }

    public function create(User $user): bool
    {
        return $user->can('watches.create');
    }

    public function update(User $user, Watch $watch): bool
    {
        return $user->can('watches.update');
    }

    public function delete(User $user, Watch $watch): bool
    {
        return $user->can('watches.delete');
    }

    public function restore(User $user, Watch $watch): bool
    {
        return $user->can('watches.delete');
    }

    public function forceDelete(User $user, Watch $watch): bool
    {
        return $user->can('watches.delete');
    }
}
