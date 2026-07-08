<?php

/**
 * =========================================================================
 * Migration: valuations — Bewertungs-Historie (Modul 7, Tenant-DB)
 * =========================================================================
 *
 * Zweck:
 *   Marktwert-Bewertungen pro Uhr über die Zeit (Wertentwicklung!).
 *   Der jeweils AKTUELLE Wert wird zusätzlich in
 *   watches.current_market_value/last_valuation_at gespiegelt
 *   (Schnellzugriff für Listen und Widgets) — Pflege ausschließlich
 *   über die RecordValuationAction.
 *
 * Design:
 *   - value_low/value_high: Marktpreis-Spanne der KI-Recherche
 *   - source (ValuationSource): manuell / KI-Recherche / extern
 *   - source_urls JSON: Belege der Recherche
 *
 * DB-agnostisch (ADR-001): nur Schema-Builder.
 * =========================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valuations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('watch_id')->constrained('watches')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('source'); // ValuationSource-Enum
            $table->decimal('market_value', 12, 2);
            $table->decimal('value_low', 12, 2)->nullable();
            $table->decimal('value_high', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->date('valued_at');
            $table->text('summary')->nullable();
            $table->json('source_urls')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['watch_id', 'valued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuations');
    }
};
