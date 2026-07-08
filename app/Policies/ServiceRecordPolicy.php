<?php

/**
 * =========================================================================
 * ServiceRecordPolicy — Autorisierung der Servicevorgänge
 * =========================================================================
 *
 * Zweck:
 *   Zugriff über die geseedeten services.*-Berechtigungen — Semantik
 *   wie im ganzen System: Lesen alle, Pflegen auch Mitarbeiter,
 *   Löschen nur Verwaltung.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceRecord;
use App\Models\User;

class ServiceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, ServiceRecord $record): bool
    {
        return $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, ServiceRecord $record): bool
    {
        return $user->can('services.update');
    }

    public function delete(User $user, ServiceRecord $record): bool
    {
        return $user->can('services.delete');
    }

    public function restore(User $user, ServiceRecord $record): bool
    {
        return $user->can('services.delete');
    }

    public function forceDelete(User $user, ServiceRecord $record): bool
    {
        return $user->can('services.delete');
    }
}
