<?php

/**
 * =========================================================================
 * DeleteTenantAction — Kontrollierte, endgültige Löschung eines Mandanten
 * =========================================================================
 *
 * Zweck:
 *   Der EINZIGE Weg, einen Mandanten samt Datenbank physisch zu löschen.
 *   Die automatische DB-Löschung wurde bewusst aus der stancl-Event-
 *   Pipeline entfernt (siehe TenancyServiceProvider), weil Tenants
 *   SoftDeletes nutzen und ein Soft Delete sonst die Datenbank
 *   vernichten würde.
 *
 * Sicherheitsmodell (zwei Stufen):
 *   - archive(): Soft Delete — Mandant verschwindet aus der Plattform,
 *     ALLE Daten (inkl. Tenant-DB) bleiben erhalten. Reversibel.
 *   - execute(): forceDelete + physisches Löschen der Tenant-Datenbank.
 *     NICHT reversibel — nur für DSGVO-Löschersuchen und endgültige
 *     Vertragsbeendigung.
 *
 * Mögliche Erweiterungen:
 *   - Backup der Tenant-DB vor der Löschung (spatie/laravel-backup)
 *   - Karenzzeit: endgültige Löschung erst X Tage nach Archivierung
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Stancl\Tenancy\Jobs\DeleteDatabase;

class DeleteTenantAction
{
    /**
     * Stufe 1: Archivieren (Soft Delete). Reversibel, keine DB-Löschung.
     */
    public function archive(Tenant $tenant): void
    {
        $tenant->update(['status' => TenantStatus::Archived]);
        $tenant->delete();
    }

    /**
     * Stufe 2: Endgültige Löschung — Tenant-Datensatz UND Tenant-Datenbank.
     *
     * WARUM DeleteDatabase VOR forceDelete: Der Job benötigt die
     * Datenbank-Metadaten des Tenants; nach dem forceDelete wäre der
     * Datensatz weg.
     */
    public function execute(Tenant $tenant): void
    {
        DeleteDatabase::dispatchSync($tenant);

        $tenant->domains()->delete();
        $tenant->forceDelete();
    }
}
