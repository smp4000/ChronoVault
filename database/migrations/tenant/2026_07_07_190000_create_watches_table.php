<?php

/**
 * =========================================================================
 * Migration: watches — Kernmodul Uhren (Modul 3, Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Zentrale Bestandstabelle der Plattform: jede physische Uhr eines
 *   Händlers/Juweliers/Auktionshauses. Referenziert die Stammdaten aus
 *   Modul 2 (brands, calibers).
 *
 * Design-Entscheidungen:
 *   - brand_id restrictOnDelete: Eine Marke mit Uhren darf nie hart
 *     gelöscht werden (Policy verhindert es bereits in der UI).
 *   - caliber_id nullable: Werk oft unbekannt/irrelevant (z. B. bei
 *     Quarz-Modeuhren) — restrictOnDelete schützt trotzdem Referenzen.
 *   - stock_number unique: interne Bestandsnummer des Betriebs (SKU);
 *     nullable, da nicht jeder Betrieb mit Nummern arbeitet (mehrere
 *     NULLs kollidieren nicht — MariaDB wie PostgreSQL).
 *   - serial_number bewusst NICHT unique: Graumarkt-Realität (unbekannte
 *     oder unvollständig erfasste Seriennummern) — nur indexiert.
 *   - KEINE Preisspalten: Einkauf/Verkauf/Historie kommen als eigene
 *     Tabellen in Modul 5 (eine Uhr kann mehrfach ge-/verkauft werden).
 *   - status indexiert: Dashboard-Kennzahlen und Bestandsfilter
 *     gruppieren/filtern ständig darüber.
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
        Schema::create('watches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignUuid('caliber_id')->nullable()->constrained('calibers')->restrictOnDelete();

            // Identifikation
            $table->string('model_name');
            $table->string('reference_number')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->string('stock_number')->nullable()->unique();
            $table->unsignedSmallInteger('production_year')->nullable();

            // Zustand & Bestandsstatus
            $table->string('condition');
            $table->string('status')->index();
            $table->boolean('has_box')->default(false);
            $table->boolean('has_papers')->default(false);

            // Gehäuse & Ausstattung
            $table->string('case_material')->nullable();
            $table->decimal('case_diameter_mm', 4, 1)->nullable();
            $table->string('dial_color')->nullable();
            $table->string('bracelet_material')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watches');
    }
};
