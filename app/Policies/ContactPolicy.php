<?php

/**
 * =========================================================================
 * ContactPolicy — Autorisierung des Kundenstamms im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Zugriff über die geseedeten contacts.*-Berechtigungen.
 *
 * Schutzregel (nicht per Berechtigung abbildbar):
 *   Kontakte mit Transaktionen sind nicht löschbar — Belege dürfen
 *   ihren Bezug nie verlieren (DB-seitig zusätzlich restrictOnDelete).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->can('contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->hasNoReferences($contact)
            && $user->can('contacts.delete');
    }

    public function restore(User $user, Contact $contact): bool
    {
        return $user->can('contacts.delete');
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        return $this->hasNoReferences($contact)
            && $user->can('contacts.delete');
    }

    /**
     * Referenz-Schutz: Kontakte mit Belegen, Servicevorgängen oder
     * Auktionskäufen (auch soft-gelöschten — die FK-Referenz existiert
     * physisch weiter) sind nicht löschbar.
     */
    private function hasNoReferences(Contact $contact): bool
    {
        return ! $contact->transactions()->withTrashed()->exists()
            && ! $contact->serviceRecords()->withTrashed()->exists()
            && ! $contact->auctionLots()->withTrashed()->exists();
    }
}
