<?php

/**
 * =========================================================================
 * ValuationPolicy — Autorisierung der Marktwert-Bewertungen
 * =========================================================================
 *
 * Zugriff über die geseedeten valuations.*-Berechtigungen — Semantik
 * wie im ganzen System.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Valuation;

class ValuationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('valuations.view');
    }

    public function view(User $user, Valuation $valuation): bool
    {
        return $user->can('valuations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('valuations.create');
    }

    public function update(User $user, Valuation $valuation): bool
    {
        return $user->can('valuations.update');
    }

    public function delete(User $user, Valuation $valuation): bool
    {
        return $user->can('valuations.delete');
    }

    public function restore(User $user, Valuation $valuation): bool
    {
        return $user->can('valuations.delete');
    }

    public function forceDelete(User $user, Valuation $valuation): bool
    {
        return $user->can('valuations.delete');
    }
}
