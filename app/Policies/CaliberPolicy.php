<?php

/**
 * =========================================================================
 * CaliberPolicy — Autorisierung der Kaliber-Stammdaten im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Regelt den Zugriff auf Kaliber über die geseedeten master_data.*-
 *   Berechtigungen (Tenant-DB) — identisch zur BrandPolicy, da beide
 *   Entitäten denselben Stammdaten-Bereich bilden.
 *
 * Hinweis:
 *   Ab Modul 3 (Watches) kommt hier ein Referenz-Schutz analog zur
 *   BrandPolicy hinzu: Kaliber, die von Uhren referenziert werden,
 *   dürfen nicht gelöscht werden.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\Caliber;
use App\Models\User;

class CaliberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master_data.view');
    }

    public function view(User $user, Caliber $caliber): bool
    {
        return $user->can('master_data.view');
    }

    public function create(User $user): bool
    {
        return $user->can('master_data.create');
    }

    public function update(User $user, Caliber $caliber): bool
    {
        return $user->can('master_data.update');
    }

    public function delete(User $user, Caliber $caliber): bool
    {
        return $user->can('master_data.delete');
    }

    public function restore(User $user, Caliber $caliber): bool
    {
        return $user->can('master_data.delete');
    }

    public function forceDelete(User $user, Caliber $caliber): bool
    {
        return $user->can('master_data.delete');
    }
}
