<?php

/**
 * =========================================================================
 * Migration: Versandkosten für Gegenangebote (price_proposals)
 * =========================================================================
 * Der Händler kann im Gegenangebot Porto aufschlagen — die Mail weist
 * Angebot + Versand + Gesamt gegliedert aus. DB-agnostisch (ADR-001).
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
        Schema::table('price_proposals', function (Blueprint $table): void {
            $table->decimal('shipping_price', 12, 2)->nullable()->after('counter_price');
        });
    }

    public function down(): void
    {
        Schema::table('price_proposals', function (Blueprint $table): void {
            $table->dropColumn('shipping_price');
        });
    }
};
