<?php

/**
 * =========================================================================
 * UserPolicy — Autorisierung der Benutzerverwaltung im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Regelt, welche Tenant-Benutzer andere Benutzer ihres Betriebs
 *   verwalten dürfen. Basiert vollständig auf den in der Tenant-DB
 *   geseedeten Berechtigungen (users.view, users.create, …) — KEINE
 *   hartkodierten Rollennamen in der Logik (Ausnahme: Schutzregeln).
 *
 * Schutzregeln (nicht per Berechtigung abbildbar):
 *   - Niemand löscht sich selbst (Aussperr-Schutz)
 *   - Nur ein Owner darf andere Owner bearbeiten/löschen
 *     (Hierarchie-Schutz: Admins können den Inhaber nicht entmachten)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update')
            && $this->respectsOwnerHierarchy($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        // Aussperr-Schutz: Der eigene Account ist nicht löschbar.
        if ($user->is($model)) {
            return false;
        }

        return $user->can('users.delete')
            && $this->respectsOwnerHierarchy($user, $model);
    }

    /**
     * Hierarchie-Schutz: Owner-Accounts dürfen nur von Ownern
     * verändert werden.
     */
    private function respectsOwnerHierarchy(User $actor, User $target): bool
    {
        if ($target->hasRole(UserRole::Owner->value)) {
            return $actor->hasRole(UserRole::Owner->value);
        }

        return true;
    }
}
