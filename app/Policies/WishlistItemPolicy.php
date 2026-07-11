<?php

/**
 * =========================================================================
 * WishlistItemPolicy — Autorisierung der Wunschliste
 * =========================================================================
 *
 * Zweck:
 *   Zugriff über die bestehenden watches.*-Berechtigungen — die
 *   Wunschliste gehört fachlich zum Bestand. BEWUSST keine neue
 *   Berechtigungs-Gruppe: bestehende Tenant-Datenbanken funktionieren
 *   ohne Nach-Seeden der Rollen sofort (Muster wie PriceProposalPolicy).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WishlistItem;

class WishlistItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('watches.view');
    }

    public function view(User $user, WishlistItem $item): bool
    {
        return $user->can('watches.view');
    }

    public function create(User $user): bool
    {
        return $user->can('watches.create');
    }

    public function update(User $user, WishlistItem $item): bool
    {
        return $user->can('watches.update');
    }

    public function delete(User $user, WishlistItem $item): bool
    {
        return $user->can('watches.delete');
    }

    public function restore(User $user, WishlistItem $item): bool
    {
        return $user->can('watches.delete');
    }

    public function forceDelete(User $user, WishlistItem $item): bool
    {
        return $user->can('watches.delete');
    }
}
