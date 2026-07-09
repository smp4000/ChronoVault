<?php

/**
 * =========================================================================
 * Migration: invoices — Rechnungen mit Nummernkreis (Modul Belege)
 * =========================================================================
 *
 * Zweck:
 *   Eine Rechnung pro Verkaufsbeleg (transactions, type=sale) mit
 *   fortlaufender, lückenloser Nummer (RE-<Jahr>-<lfd. Nr.>) und
 *   UNVERÄNDERLICHEM Daten-Snapshot (seller/buyer/line als JSON):
 *   Ändern sich später Betriebsdaten, Kontakt oder Uhr, bleibt die
 *   Rechnung reproduzierbar wie am Ausstellungstag (GoBD-Gedanke).
 *
 * Design-Entscheidungen:
 *   - transaction_id UNIQUE: genau eine Rechnung je Verkauf.
 *   - KEINE SoftDeletes: Rechnungen werden nicht gelöscht (Storno wäre
 *     ein eigener Beleg — Erweiterung).
 *   - tax_mode: differential (§ 25a) / regular (19 %) / small_business
 *     (§ 19) — Beträge werden bei Erstellung eingefroren.
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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->unique()->constrained('transactions')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('invoice_number')->unique(); // RE-2026-0001
            $table->date('issued_at');
            $table->date('delivery_date')->nullable();

            $table->string('tax_mode'); // differential | regular | small_business
            $table->decimal('net_amount', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('EUR');

            // Unveränderliche Snapshots (GoBD): Stand zum Ausstellungstag
            $table->json('seller');
            $table->json('buyer');
            $table->json('line');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
