<?php

/**
 * =========================================================================
 * Migration: auction_lots.lot_code — eindeutiger Los-Code (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Öffentliche Los-Kennung als 6 GROSSBUCHSTABEN (z. B. "KXQWBA") —
 *   eindeutig über alle Auktionen des Mandanten. Ersetzt die numerische
 *   Losnummer in der Außendarstellung (Katalog, Mails, Verwendungszweck);
 *   lot_number bleibt für die Katalog-Reihenfolge erhalten.
 *
 * Bestandsdaten werden direkt beim Migrieren mit Codes befüllt,
 * danach greift der Unique-Index.
 *
 * DB-agnostisch (ADR-001): nur Schema-Builder + Query-Builder.
 * =========================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_lots', function (Blueprint $table) {
            $table->string('lot_code', 6)->nullable()->after('lot_number');
        });

        // Bestehende Lose nachziehen (inkl. soft-gelöschter)
        $used = [];

        foreach (DB::table('auction_lots')->whereNull('lot_code')->pluck('id') as $id) {
            do {
                $code = '';

                for ($i = 0; $i < 6; $i++) {
                    $code .= chr(random_int(65, 90)); // A–Z
                }
            } while (in_array($code, $used, true));

            $used[] = $code;

            DB::table('auction_lots')->where('id', $id)->update(['lot_code' => $code]);
        }

        Schema::table('auction_lots', function (Blueprint $table) {
            $table->unique('lot_code');
        });
    }

    public function down(): void
    {
        Schema::table('auction_lots', function (Blueprint $table) {
            $table->dropUnique(['lot_code']);
            $table->dropColumn('lot_code');
        });
    }
};
