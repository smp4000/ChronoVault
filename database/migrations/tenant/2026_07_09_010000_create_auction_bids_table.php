<?php

/**
 * =========================================================================
 * Migration: auction_bids — Online-Gebote auf Auktionslose (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Gebote aus dem öffentlichen Auktionskatalog (Tenant-Domain).
 *   Bieter identifizieren sich leichtgewichtig per Name + E-Mail —
 *   bewusst OHNE Konto/Login (v1): Das Auktionshaus prüft die Gebote
 *   und erteilt den Zuschlag manuell (SettleLotAction).
 *
 * Design-Entscheidungen:
 *   - auction_lot_id cascadeOnDelete: Gebote sind Teil des Loses;
 *     zugeschlagene Lose sind ohnehin nicht löschbar (Policy).
 *   - ip_address: Nachvollziehbarkeit bei Missbrauch (nur intern sichtbar).
 *   - KEIN Status-Feld: Höchstgebot = max(amount) — einfacher und
 *     race-sicher (Vergabe unter DB-Transaktion in der PlaceBidAction).
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
        Schema::create('auction_bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auction_lot_id')->constrained('auction_lots')->cascadeOnDelete();

            $table->string('bidder_name');
            $table->string('bidder_email');
            $table->string('bidder_phone')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('ip_address', 45)->nullable(); // IPv6-tauglich

            $table->timestamps();

            $table->index(['auction_lot_id', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_bids');
    }
};
