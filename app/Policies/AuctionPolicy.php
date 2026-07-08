<?php

/**
 * =========================================================================
 * AuctionPolicy — Autorisierung der Auktionen
 * =========================================================================
 *
 * Zweck:
 *   Zugriff über die geseedeten auctions.*-Berechtigungen — Semantik wie
 *   im ganzen System: Lesen alle, Pflegen auch Mitarbeiter, Löschen nur
 *   Verwaltung. Zusätzlich Referenz-Schutz: Auktionen mit offenen Losen
 *   sind nicht löschbar (die Uhren stünden sonst dauerhaft "In Auktion").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AuctionLotStatus;
use App\Models\Auction;
use App\Models\User;

class AuctionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('auctions.view');
    }

    public function view(User $user, Auction $auction): bool
    {
        return $user->can('auctions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('auctions.create');
    }

    public function update(User $user, Auction $auction): bool
    {
        return $user->can('auctions.update');
    }

    public function delete(User $user, Auction $auction): bool
    {
        return $user->can('auctions.delete')
            && ! $this->hasOpenLots($auction);
    }

    public function restore(User $user, Auction $auction): bool
    {
        return $user->can('auctions.delete');
    }

    public function forceDelete(User $user, Auction $auction): bool
    {
        return $user->can('auctions.delete')
            && ! $this->hasOpenLots($auction);
    }

    /**
     * Offene (nicht abgerechnete) Lose — inkl. soft-gelöschter, damit
     * kein Schlupfloch über den Papierkorb entsteht.
     */
    private function hasOpenLots(Auction $auction): bool
    {
        return $auction->lots()
            ->withTrashed()
            ->where('status', AuctionLotStatus::Open->value)
            ->exists();
    }
}
