<?php

/**
 * =========================================================================
 * Migration: Preissenkungs-Felder für Uhren (Streichpreis im Shop)
 * =========================================================================
 *
 * Zweck:
 *   Wird der Angebotspreis (asking_price) gesenkt, merkt sich der
 *   WatchObserver hier den vorherigen Preis + Zeitpunkt. Der Shop zeigt
 *   dann Rabatt-Badge, Streichpreis, Ersparnis und den Hinweis
 *   „Preis der letzten 30 Tage vor Preissenkung" (PAngV-Gedanke).
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
        Schema::table('watches', function (Blueprint $table): void {
            $table->decimal('previous_asking_price', 12, 2)->nullable()->after('asking_price');
            $table->timestamp('price_reduced_at')->nullable()->after('previous_asking_price');
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table): void {
            $table->dropColumn(['previous_asking_price', 'price_reduced_at']);
        });
    }
};
