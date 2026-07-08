<?php

/**
 * =========================================================================
 * Migration: auctions + auction_lots — Auktionen (Modul 8)
 * =========================================================================
 *
 * Zweck:
 *   Auktions-Ereignisse (Saal/Online/Hybrid) mit Losen: Jedes Los
 *   verknüpft eine Uhr mit Losnummer, Schätzpreis-Spanne, Limit
 *   (reserve_price) und späterem Zuschlag (hammer_price).
 *
 * Design-Entscheidungen:
 *   - previous_watch_status am LOS (nicht an der Auktion): Beim Einliefern
 *     wird der Uhren-Status gemerkt und bei Rückgang/Rückzug
 *     WIEDERHERGESTELLT — eine Kommissionsuhr kommt als Kommission zurück.
 *   - watch_id restrictOnDelete: Uhren mit Auktionshistorie bleiben.
 *   - auction_id cascadeOnDelete: Lose sind Teil der Auktion; das
 *     endgültige Löschen schützt die Policy (nur ohne offene Lose).
 *   - buyer_contact_id restrictOnDelete: Käufer-Bezüge bleiben erhalten
 *     (Referenz-Schutz zusätzlich in der ContactPolicy).
 *   - unique(auction_id, lot_number): Losnummern pro Auktion eindeutig.
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
        Schema::create('auctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('venue')->default('saleroom'); // AuctionVenue-Enum
            $table->string('location')->nullable();
            $table->string('status')->default('draft'); // AuctionStatus-Enum
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('auction_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignUuid('watch_id')->constrained('watches')->restrictOnDelete();
            $table->foreignUuid('buyer_contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('lot_number');
            $table->string('status')->default('open'); // AuctionLotStatus-Enum
            $table->string('previous_watch_status')->nullable(); // Restore bei Rückgang/Rückzug

            $table->decimal('starting_price', 12, 2)->nullable();
            $table->decimal('estimate_low', 12, 2)->nullable();
            $table->decimal('estimate_high', 12, 2)->nullable();
            $table->decimal('reserve_price', 12, 2)->nullable(); // Limit des Einlieferers
            $table->decimal('hammer_price', 12, 2)->nullable(); // Zuschlag
            $table->string('currency', 3)->default('EUR');
            $table->dateTime('settled_at')->nullable(); // Zuschlag/Rückgang erfasst am
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['auction_id', 'lot_number']);
            $table->index(['auction_id', 'status']);
            $table->index(['watch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_lots');
        Schema::dropIfExists('auctions');
    }
};
