<?php

/**
 * =========================================================================
 * Migration (ZENTRAL): marketplace_listings — Spiegel der Händler-Uhren
 * =========================================================================
 * Der zentrale Marktplatz (chrono-save.de) kann nicht über alle
 * Tenant-Datenbanken hinweg abfragen (eine DB je Mandant, ADR).
 * Deshalb spiegelt jede veröffentlichte, KAUFBARE Uhr eine Zeile in
 * diese zentrale Tabelle (Sync: WatchObserver + marketplace:sync).
 * Nur Anzeige-Daten — die Wahrheit bleibt in der Tenant-DB; Kauf und
 * Detailseite laufen im Shop des jeweiligen Verkäufers (detail_url).
 * DB-agnostisch (MariaDB lokal, PostgreSQL Produktion — ADR-001).
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
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Herkunft: Mandant + Uhr (eine Zeile je Uhr)
            $table->string('tenant_id');
            $table->uuid('watch_id');
            $table->unique(['tenant_id', 'watch_id']);

            // Verkäufer (eBay-Prinzip: privat UND gewerblich)
            $table->string('seller_name');
            $table->string('seller_type')->default('commercial'); // commercial|private
            $table->string('shop_url');
            $table->string('detail_url');

            // Anzeige-Daten der Uhr (denormalisiert für schnelle Listen)
            $table->string('brand_name');
            $table->string('model_name');
            $table->string('reference_number')->nullable();
            $table->string('year_label')->nullable();
            $table->string('condition_label')->nullable();
            $table->string('material_label')->nullable();
            $table->string('diameter_label')->nullable();
            $table->boolean('has_box')->default(false);
            $table->boolean('has_papers')->default(false);
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('previous_price', 12, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->string('photo_url', 2048)->nullable();

            $table->timestamp('listed_at')->nullable();
            $table->timestamps();

            $table->index('brand_name');
            $table->index('price');
            $table->index('seller_type');
            $table->index('listed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
