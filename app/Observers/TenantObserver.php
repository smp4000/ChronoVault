<?php

/**
 * =========================================================================
 * TenantObserver — Lifecycle-Hooks des Tenant-Models
 * =========================================================================
 *
 * Zweck:
 *   Kapselt Neben-Logik des Tenant-Lebenszyklus, damit weder das Model
 *   noch die Filament Resource damit belastet werden (Single
 *   Responsibility).
 *
 * Verantwortlichkeiten:
 *   - Slug-Generierung aus dem Namen, falls kein Slug angegeben wurde
 *     (inkl. Kollisionsauflösung durch Zähler-Suffix)
 *
 * WARUM im Observer statt im Model/Resource:
 *   Die Slug-Logik muss überall greifen — egal ob der Tenant über die
 *   Filament-UI, einen Seeder, die API oder Tinker erstellt wird.
 *
 * Mögliche Erweiterungen:
 *   - Benachrichtigung des Plattform-Teams bei neuen Tenants (created)
 *   - Aufräum-Jobs beim Archivieren (deleted/soft delete)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantObserver
{
    /**
     * Vor dem ersten Speichern: Slug sicherstellen.
     *
     * Kollisionsstrategie: "juwelier-mueller", "juwelier-mueller-2", ...
     * — deterministisch und für Subdomains geeignet.
     */
    public function creating(Tenant $tenant): void
    {
        if (blank($tenant->slug)) {
            $base = Str::slug($tenant->name);
            $slug = $base;
            $counter = 2;

            while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$counter}";
                $counter++;
            }

            $tenant->slug = $slug;
        }
    }
}
