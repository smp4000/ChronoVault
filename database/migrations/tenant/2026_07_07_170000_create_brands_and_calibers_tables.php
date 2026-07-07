<?php

/**
 * =========================================================================
 * Migration: brands & calibers — Stammdaten (Modul 2, Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Stammdaten-Tabellen für Uhrenmarken (brands) und Uhrwerke/Kaliber
 *   (calibers) in der TENANT-Datenbank (ADR-007/ADR-009: Stammdaten
 *   liegen pro Mandant, kein zentraler Katalog).
 *
 * Design-Entscheidungen:
 *   - UUID-Primärschlüssel: Domänenentitäten, die später in Exporten,
 *     APIs und URLs auftauchen (Projektregel).
 *   - SoftDeletes: Marken/Kaliber werden von Uhren (Modul 3) referenziert —
 *     gelöschte Stammdaten dürfen historische Datensätze nicht zerstören.
 *   - calibers.brand_id → restrictOnDelete: Ein HARTES Löschen einer Marke
 *     mit Kalibern schlägt auf DB-Ebene fehl (letzte Verteidigungslinie;
 *     die BrandPolicy verhindert es bereits in der UI).
 *   - unique(brand_id, name): Kalibernamen (z. B. "ETA 2824-2") sind nur
 *     innerhalb ihrer Marke eindeutig, nicht global.
 *
 * DB-agnostisch (ADR-001): nur Schema-Builder, keine Raw-SQL-Statements.
 * =========================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('country')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('calibers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('brand_id')->constrained('brands')->restrictOnDelete();
            $table->string('name');
            $table->string('movement_type');
            $table->unsignedSmallInteger('power_reserve_hours')->nullable();
            $table->unsignedInteger('frequency_vph')->nullable();
            $table->unsignedTinyInteger('jewels')->nullable();
            $table->decimal('diameter_mm', 4, 1)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Kalibername nur innerhalb der Marke eindeutig.
            $table->unique(['brand_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibers');
        Schema::dropIfExists('brands');
    }
};
