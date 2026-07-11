<?php

/**
 * =========================================================================
 * Migration: Wunschliste wandert in die Uhren (Status "wishlist")
 * =========================================================================
 *
 * Zweck:
 *   Wunschmodelle sind jetzt normale Uhren mit Status "Wunschliste" —
 *   mit allen Werkzeugen (KI-Lookup, Fotos, nächtliche Bewertung).
 *   Neue Felder: Zielpreis + Alarm-Sperre. Die separate
 *   wishlist_items-Tabelle (kurzlebiges Zwischenmodell) entfällt.
 *   DB-agnostisch (ADR-001).
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
        Schema::table('watches', function (Blueprint $table): void {
            $table->decimal('wishlist_target_price', 12, 2)->nullable()->after('current_market_value');
            $table->timestamp('wishlist_notified_at')->nullable()->after('wishlist_target_price');
        });

        Schema::dropIfExists('wishlist_items');
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table): void {
            $table->dropColumn(['wishlist_target_price', 'wishlist_notified_at']);
        });
    }
};
