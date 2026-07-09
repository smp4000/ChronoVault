<?php

/**
 * =========================================================================
 * Migration: Gegenangebot-Preis für Preisvorschläge
 * =========================================================================
 * Beim Gegenangebot des Händlers wird der angebotene Preis am Vorschlag
 * gespeichert (Nachvollziehbarkeit im Panel). DB-agnostisch (ADR-001).
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
            $table->decimal('counter_price', 12, 2)->nullable()->after('asking_price_at_time');
        });
    }

    public function down(): void
    {
        Schema::table('price_proposals', function (Blueprint $table): void {
            $table->dropColumn('counter_price');
        });
    }
};
