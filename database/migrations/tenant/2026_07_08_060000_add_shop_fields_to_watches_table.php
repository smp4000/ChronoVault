<?php

/**
 * =========================================================================
 * Migration: watches — Shop-Felder (öffentliches Schaufenster)
 * =========================================================================
 *
 * Zweck:
 *   - is_published: Uhr im öffentlichen Shop sichtbar (Opt-in! Händler
 *     entscheiden pro Uhr — Kommissions-/Kundenware bleibt sonst intern).
 *   - asking_price: öffentlicher VERKAUFSPREIS — bewusst getrennt vom
 *     internen purchase_price und vom recherchierten current_market_value.
 *     Ohne Preis zeigt der Shop "Preis auf Anfrage".
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
            $table->boolean('is_published')->default(false)->index()->after('status');
            $table->decimal('asking_price', 12, 2)->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropColumn(['is_published', 'asking_price']);
        });
    }
};
