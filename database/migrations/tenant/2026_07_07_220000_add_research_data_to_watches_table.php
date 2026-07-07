<?php

/**
 * =========================================================================
 * Migration: watches.research_data — KI-Rechercheergebnisse (Modul 3)
 * =========================================================================
 *
 * Zweck:
 *   JSON-Spalte für das Rohergebnis des KI-Referenz-Lookups
 *   (WatchReferenceLookupService): Beschreibung, Bild-URLs und
 *   Quellen-URLs. Modul 4 (Medienverwaltung) nutzt die Bild-URLs,
 *   um Fotos in die Media Library zu übernehmen.
 *
 * DB-agnostisch (ADR-001): json() wird von MariaDB wie PostgreSQL
 * unterstützt (MariaDB speichert als LONGTEXT mit JSON-Validierung).
 * =========================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->json('research_data')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropColumn('research_data');
        });
    }
};
