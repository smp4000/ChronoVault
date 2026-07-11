<?php

/**
 * =========================================================================
 * Migration: Wunschliste (wishlist_items) — Sammler-Beobachtung
 * =========================================================================
 *
 * Zweck:
 *   Wunschmodelle, die der Sammler/Händler NOCH NICHT besitzt: Marke,
 *   Modell, Referenz, Zielpreis. Die nächtliche KI-Wertermittlung
 *   aktualisiert den Marktwert; bei Erreichen des Zielpreises geht
 *   eine Alarm-Mail raus (notified_at verhindert Mail-Spam, wird bei
 *   Preisen über Ziel wieder scharfgestellt).
 *   DB-agnostisch (MariaDB lokal, PostgreSQL Produktion — ADR-001).
 * =========================================================================
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('brand_id')->constrained()->restrictOnDelete();
            $table->string('model_name');
            $table->string('reference_number')->nullable();
            $table->decimal('target_price', 12, 2)->nullable();
            $table->string('status')->default('active');
            $table->decimal('current_market_value', 12, 2)->nullable();
            $table->decimal('value_low', 12, 2)->nullable();
            $table->decimal('value_high', 12, 2)->nullable();
            $table->timestamp('last_valuation_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'last_valuation_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
