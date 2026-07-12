<?php

/**
 * =========================================================================
 * CreateTenantAction — Vollständiges Provisioning eines neuen Mandanten
 * =========================================================================
 *
 * Zweck:
 *   Der EINZIGE Weg, einen Mandanten anzulegen — egal ob aus der
 *   Filament-UI, einem Seeder, der API oder Tinker. Kapselt den gesamten
 *   Ablauf, damit keine halbfertigen Mandanten entstehen können.
 *
 * Ablauf:
 *   1. Tenant-Datensatz anlegen (zentrale DB)
 *      → stancl-Pipeline feuert automatisch: CreateDatabase →
 *        MigrateDatabase → SeedDatabase (Rollen & Berechtigungen)
 *   2. Domain registrieren ({slug}.{tenant_domain_suffix})
 *   3. Owner-Benutzer IN der frischen Tenant-DB anlegen und ihm die
 *      Owner-Rolle zuweisen
 *
 * WARUM eine Action statt Service:
 *   Ein einzelner, atomarer Use-Case mit genau einer öffentlichen
 *   Methode — das klassische Action-Pattern. Ein TenantService mit
 *   vielen Methoden würde hier nur künstlich bündeln.
 *
 * Fehlerverhalten:
 *   Wirft Exceptions weiter (kein stilles Scheitern). Die DB-Erstellung
 *   läuft lokal synchron (shouldBeQueued(false)) — schlägt sie fehl,
 *   bricht der gesamte Vorgang ab.
 *
 * Mögliche Erweiterungen:
 *   - Willkommens-E-Mail an den Owner (Event TenantProvisioned)
 *   - Trial-Ablaufdatum setzen (Abrechnungs-Modul)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

class CreateTenantAction
{
    /**
     * Legt einen Mandanten inkl. Datenbank, Domain und Owner-Benutzer an.
     *
     * @param  string  $name  Firmenname (z. B. "Juwelier Müller GmbH")
     * @param  string  $ownerName  Name des Inhaber-Benutzers
     * @param  string  $ownerEmail  Login-E-Mail des Inhabers
     * @param  string  $ownerPassword  Initialpasswort (bereits validiert!)
     * @param  string|null  $slug  Optionaler Wunsch-Slug; sonst aus dem Namen generiert (TenantObserver)
     * @param  string  $sellerType  Marktplatz-Verkäufertyp: 'commercial' oder 'private' (eBay-Prinzip)
     */
    public function execute(
        string $name,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
        ?string $slug = null,
        TenantStatus $status = TenantStatus::Trial,
        string $sellerType = 'commercial',
    ): Tenant {
        // 1. Tenant anlegen — die stancl-Pipeline (CreateDatabase, Migrate,
        //    Seed) läuft hier SYNCHRON mit. Nach dieser Zeile existiert die
        //    fertige, geseedete Tenant-Datenbank. seller_type landet im
        //    data-JSON (Custom Columns) und steuert das Marktplatz-Badge.
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'seller_type' => $sellerType,
        ]);

        // 2. Domain registrieren: slug.localhost (lokal) bzw.
        //    slug.chronovault.app (Produktion, via TENANT_DOMAIN_SUFFIX).
        $tenant->domains()->create([
            'domain' => $tenant->slug.'.'.config('chronovault.tenant_domain_suffix'),
        ]);

        // 3. Owner-Benutzer IN der Tenant-DB anlegen. $tenant->run() wechselt
        //    temporär den kompletten Datenbank-Kontext auf diesen Mandanten.
        $tenant->run(function () use ($ownerName, $ownerEmail, $ownerPassword): void {
            $owner = User::create([
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => $ownerPassword, // 'hashed'-Cast im Model übernimmt das Hashing
            ]);

            $owner->assignRole(UserRole::Owner->value);
        });

        return $tenant;
    }
}
