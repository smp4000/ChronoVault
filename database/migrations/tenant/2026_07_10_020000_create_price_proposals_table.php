<?php

/**
 * =========================================================================
 * Migration: Preisvorschläge aus dem Shop (price_proposals)
 * =========================================================================
 *
 * Zweck:
 *   Jeder Preisvorschlag von der Shop-Detailseite wird zusätzlich zur
 *   Mail als Datensatz gespeichert — der Händler sieht und beantwortet
 *   ihn im Panel (Filament-Ressource „Preisvorschläge").
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
        Schema::create('price_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('watch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->decimal('proposed_price', 12, 2);
            // Angebotspreis zum Zeitpunkt des Vorschlags (Vergleichswert,
            // auch wenn der Preis später geändert wird)
            $table->decimal('asking_price_at_time', 12, 2)->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_proposals');
    }
};
