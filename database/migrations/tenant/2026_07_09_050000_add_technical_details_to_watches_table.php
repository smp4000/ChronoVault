<?php

/**
 * =========================================================================
 * Migration: watches — weitere technische Details (Vorbild Hersteller-
 * Produktseiten, z. B. TAG Heuer: Bandanstoß-Abstand, Lünettentyp,
 * Gehäuseboden, Zifferblatt-Finish)
 * =========================================================================
 *
 * Gangreserve/Frequenz/Steine leben bewusst am KALIBER (calibers) —
 * das sind Werk-Eigenschaften, keine Uhren-Eigenschaften.
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
        Schema::table('watches', function (Blueprint $table) {
            $table->decimal('lug_to_lug_mm', 5, 2)->nullable()->after('lug_width_mm'); // Bandanstoß zu Bandanstoß
            $table->string('bezel_type')->nullable()->after('bezel_color'); // BezelType-Enum
            $table->string('case_back')->nullable()->after('bezel_type'); // CaseBack-Enum
            $table->string('dial_finish')->nullable()->after('dial_numerals'); // Freitext (z. B. "Opalin & lackiert")
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropColumn(['lug_to_lug_mm', 'bezel_type', 'case_back', 'dial_finish']);
        });
    }
};
