<?php

/**
 * =========================================================================
 * Migration: tenants — Zentrale Mandanten-Tabelle (stancl/tenancy)
 * =========================================================================
 *
 * Zweck:
 *   Speichert alle Mandanten (Händler, Juweliere, Auktionshäuser) der
 *   Plattform in der ZENTRALEN Datenbank. Jeder Mandant erhält eine
 *   eigene, physisch getrennte Datenbank (Multi-Database-Tenancy,
 *   siehe ADR-007 in docs/DECISIONS.md).
 *
 * Spalten:
 *   - id      : UUID (string) — von stancl generiert. WARUM UUID: Die ID
 *               ist Bestandteil des Tenant-Datenbanknamens und darf nicht
 *               erratbar/aufzählbar sein.
 *   - name    : Anzeigename des Betriebs (z. B. "Juwelier Müller GmbH").
 *   - slug    : URL-sicherer, eindeutiger Kurzname — bildet die Subdomain
 *               (slug.localhost lokal, slug.chronovault.app in Produktion).
 *   - status  : Lebenszyklus des Mandanten (App\Enums\TenantStatus).
 *               Index, da Middleware/Queries häufig danach filtern.
 *   - data    : JSON-Spalte von stancl für virtuelle Attribute
 *               (alles, was keine eigene Spalte verdient).
 *
 * WARUM SoftDeletes:
 *   Das Löschen eines Tenants ist die gefährlichste Operation der
 *   Plattform (Datenbank-Löschung!). Soft Delete = Archivierung ohne
 *   Datenverlust. Die physische DB wird ausschließlich über die
 *   explizite Force-Delete-Aktion entfernt (App\Actions\Tenancy\DeleteTenantAction).
 * =========================================================================
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active')->index();

            $table->timestamps();
            $table->softDeletes();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
