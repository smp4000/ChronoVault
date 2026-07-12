<?php

/**
 * =========================================================================
 * Migration (ZENTRAL): Detail-Felder für marketplace_listings
 * =========================================================================
 * Privatverkäufer laufen nach dem eBay-Prinzip KOMPLETT über die
 * zentrale Plattform: ihre Angebote bekommen eine zentrale Detailseite
 * (/angebot/{listing}) statt eines eigenen Shops. Dafür braucht der
 * Spiegel Beschreibung und ALLE Foto-URLs (bisher nur das Titelbild).
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
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->text('description')->nullable()->after('photo_url');
            $table->json('photo_urls')->nullable()->after('description');
            // Sofortkauf je Angebot (Privat: nur mit hinterlegter IBAN)
            $table->boolean('direct_buy')->default(true)->after('photo_urls');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn(['description', 'photo_urls', 'direct_buy']);
        });
    }
};
