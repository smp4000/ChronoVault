<?php

/**
 * =========================================================================
 * Migration (TENANT): watches.allow_direct_buy — Sofortkauf je Uhr
 * =========================================================================
 * eBay-Prinzip: Der Verkäufer entscheidet je Uhr, ob sie per Sofortkauf
 * verkauft werden darf oder nur über Anfrage/Preisvorschlag. Bei
 * Privatverkäufern greift der Sofortkauf zusätzlich nur, wenn eine
 * Bankverbindung hinterlegt ist (der Käufer überweist direkt).
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
        Schema::table('watches', function (Blueprint $table) {
            $table->boolean('allow_direct_buy')->default(true)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropColumn('allow_direct_buy');
        });
    }
};
