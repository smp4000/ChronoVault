<?php

/**
 * =========================================================================
 * AuctionLotPolicy — Autorisierung der Auktionslose
 * =========================================================================
 *
 * Zweck:
 *   Lose teilen den Berechtigungssatz der Auktionen (auctions.*) —
 *   ein eigenes Rechte-Set brächte keinen Sicherheitsgewinn, nur
 *   Pflegeaufwand. Löschen nur für nicht zugeschlagene Lose: Der
 *   Verkaufsbeleg (Modul 5) referenziert sonst ein gelöschtes Los-Umfeld.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuctionLot;
use App\Models\User;

class AuctionLotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('auctions.view');
    }

    public function view(User $user, AuctionLot $lot): bool
    {
        return $user->can('auctions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('auctions.update');
    }

    public function update(User $user, AuctionLot $lot): bool
    {
        return $user->can('auctions.update');
    }

    public function delete(User $user, AuctionLot $lot): bool
    {
        // Zugeschlagene Lose sind Beleg-Historie (Transaction existiert).
        return $user->can('auctions.delete') && ! $lot->isSold();
    }

    public function restore(User $user, AuctionLot $lot): bool
    {
        return $user->can('auctions.delete');
    }

    public function forceDelete(User $user, AuctionLot $lot): bool
    {
        return $user->can('auctions.delete') && ! $lot->isSold();
    }
}
