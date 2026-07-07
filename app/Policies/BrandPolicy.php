<?php

/**
 * =========================================================================
 * BrandPolicy — Autorisierung der Marken-Stammdaten im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Regelt den Zugriff auf Marken über die geseedeten master_data.*-
 *   Berechtigungen (Tenant-DB). KEINE hartkodierten Rollennamen —
 *   Mandanten können eigene Rollen mit diesen Rechten ausstatten.
 *
 * Standard-Zuordnung (TenantDatabaseSeeder):
 *   view → alle Rollen · create/update → Owner/Admin/Employee ·
 *   delete → Owner/Admin
 *
 * Schutzregel (nicht per Berechtigung abbildbar):
 *   Marken mit Kalibern sind nicht löschbar — Kaliber (und ab Modul 3
 *   Uhren) würden ihre Herstellerreferenz verlieren. Der Nutzer muss
 *   erst die Kaliber entfernen; alternativ die Marke deaktivieren
 *   (is_active), um sie aus Auswahlfeldern auszublenden.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master_data.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->can('master_data.view');
    }

    public function create(User $user): bool
    {
        return $user->can('master_data.create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can('master_data.update');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->hasNoReferences($brand)
            && $user->can('master_data.delete');
    }

    public function restore(User $user, Brand $brand): bool
    {
        return $user->can('master_data.delete');
    }

    public function forceDelete(User $user, Brand $brand): bool
    {
        // Referenz-Schutz wie bei delete — zusätzlich greift auf DB-Ebene
        // restrictOnDelete (letzte Verteidigungslinie).
        return $this->hasNoReferences($brand)
            && $user->can('master_data.delete');
    }

    /**
     * Referenz-Schutz: Marken mit Kalibern oder Uhren dürfen nicht
     * gelöscht werden — abhängige Datensätze würden ihren Hersteller
     * verlieren. withTrashed: auch soft-gelöschte Uhren zählen, ihre
     * brand_id-Referenz existiert physisch weiter.
     */
    private function hasNoReferences(Brand $brand): bool
    {
        return ! $brand->calibers()->withTrashed()->exists()
            && ! $brand->watches()->withTrashed()->exists();
    }
}
