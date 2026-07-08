<?php

/**
 * =========================================================================
 * Migration: auctions.bid_increment — einstellbarer Mindest-Schritt (8b)
 * =========================================================================
 *
 * Zweck:
 *   Der Mindest-Erhöhungsschritt für Online-Gebote ist pro Auktion
 *   einstellbar (Wunsch des Auftraggebers: z. B. 100 € oder ein anderer
 *   Betrag). Gebote müssen das Höchstgebot um mindestens diesen Betrag
 *   übertreffen; der Gebotsbetrag selbst bleibt frei wählbar.
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
        Schema::table('auctions', function (Blueprint $table) {
            $table->decimal('bid_increment', 8, 2)->default(100)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('bid_increment');
        });
    }
};
