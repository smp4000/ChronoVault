<?php

/**
 * =========================================================================
 * PriceProposalPolicy — Autorisierung der Preisvorschläge
 * =========================================================================
 *
 * Zweck:
 *   Zugriff über die bestehenden watches.*-Berechtigungen — Vorschläge
 *   gehören fachlich zum Bestand/Verkauf. BEWUSST keine neue
 *   Berechtigungs-Gruppe: bestehende Tenant-Datenbanken funktionieren
 *   ohne Nach-Seeden der Rollen sofort.
 *
 *   create() ist verboten — Vorschläge entstehen ausschließlich über
 *   den öffentlichen Shop (ShopController::propose).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\PriceProposal;
use App\Models\User;

class PriceProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('watches.view');
    }

    public function view(User $user, PriceProposal $proposal): bool
    {
        return $user->can('watches.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PriceProposal $proposal): bool
    {
        return $user->can('watches.update');
    }

    public function delete(User $user, PriceProposal $proposal): bool
    {
        return $user->can('watches.delete');
    }

    public function restore(User $user, PriceProposal $proposal): bool
    {
        return $user->can('watches.delete');
    }

    public function forceDelete(User $user, PriceProposal $proposal): bool
    {
        return $user->can('watches.delete');
    }
}
