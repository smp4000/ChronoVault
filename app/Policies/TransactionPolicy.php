<?php

/**
 * =========================================================================
 * TransactionPolicy — Autorisierung der Kauf-/Verkaufsbelege
 * =========================================================================
 *
 * Zweck:
 *   Zugriff über die geseedeten transactions.*-Berechtigungen.
 *   Belege sind Ewigkeitsdaten: Löschen ist der Verwaltung vorbehalten
 *   (Standard-Seed: Owner/Admin) und bedeutet SoftDelete (Storno);
 *   forceDelete bleibt möglich, aber ebenfalls verwaltungsexklusiv.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transactions.view');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->can('transactions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('transactions.create');
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->can('transactions.update');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->can('transactions.delete');
    }

    public function restore(User $user, Transaction $transaction): bool
    {
        return $user->can('transactions.delete');
    }

    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return $user->can('transactions.delete');
    }
}
