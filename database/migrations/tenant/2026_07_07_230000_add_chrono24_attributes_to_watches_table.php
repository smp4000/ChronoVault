<?php

/**
 * =========================================================================
 * Migration: watches — Chrono24-Attributkatalog (Modul 3)
 * =========================================================================
 *
 * Zweck:
 *   Erweitert die Uhren um die standardisierten Inserat-Attribute nach
 *   Chrono24-Vorbild (Aufzug, Geschlecht, Glas, Lünette, Zifferblatt-
 *   Zahlen, Armbandfarbe, Schließe, Wasserdichtigkeit, zweite Gehäuse-
 *   dimension, Bandanstoß, „Baujahr ungefähr").
 *
 *   Die bisherigen FREITEXT-Spalten case_material, dial_color und
 *   bracelet_material werden auf Enum-Codes umgestellt: bekannte deutsche
 *   Begriffe werden gemappt, alles Unbekannte wird genullt (Datenqualität
 *   vor Datenerhalt — es existieren nur Dev-/Demo-Daten).
 *
 * DB-agnostisch (ADR-001): Schema-Builder + Query-Builder, kein Raw-SQL.
 * =========================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapping der bisherigen Freitextwerte (Factory/KI, deutsch) auf
     * Enum-Codes — pro Spalte, unbekannte Werte werden genullt.
     *
     * @var array<string, array<string, string>>
     */
    private const VALUE_MAP = [
        'case_material' => [
            'Edelstahl' => 'steel',
            'Gelbgold 18k' => 'yellow_gold',
            'Roségold 18k' => 'rose_gold',
            'Titan' => 'titanium',
            'Platin' => 'platinum',
        ],
        'dial_color' => [
            'Schwarz' => 'black',
            'Weiß' => 'white',
            'Blau' => 'blue',
            'Silber' => 'silver',
            'Grün' => 'green',
        ],
        'bracelet_material' => [
            'Edelstahl' => 'steel',
            'Leder' => 'leather',
            'Kautschuk' => 'rubber',
            'Gold/Stahl' => 'gold_steel',
            'Oyster-Band Edelstahl' => 'steel',
        ],
    ];

    public function up(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            // Aufzug auf Uhr-Ebene: relevant, wenn kein Kaliber erfasst ist
            // (Chrono24 fragt den Aufzug immer direkt ab).
            $table->string('movement_type')->nullable()->after('caliber_id');

            $table->string('gender')->nullable()->after('production_year');
            $table->boolean('is_production_year_approximate')->default(false)->after('production_year');

            // Gehäuse
            $table->decimal('case_height_mm', 4, 1)->nullable()->after('case_diameter_mm');
            $table->string('glass_type')->nullable()->after('case_height_mm');
            $table->string('bezel_material')->nullable()->after('glass_type');
            $table->string('bezel_color')->nullable()->after('bezel_material');
            $table->unsignedTinyInteger('water_resistance_bar')->nullable()->after('bezel_color');

            // Zifferblatt & Band
            $table->string('dial_numerals')->nullable()->after('dial_color');
            $table->string('bracelet_color')->nullable()->after('bracelet_material');
            $table->string('clasp_type')->nullable()->after('bracelet_color');
            $table->string('clasp_material')->nullable()->after('clasp_type');
            $table->unsignedTinyInteger('lug_width_mm')->nullable()->after('clasp_material');
        });

        // Freitext → Enum-Codes: bekannte Werte mappen, Rest nullen.
        foreach (self::VALUE_MAP as $column => $map) {
            foreach ($map as $old => $code) {
                DB::table('watches')->where($column, $old)->update([$column => $code]);
            }

            DB::table('watches')
                ->whereNotNull($column)
                ->whereNotIn($column, array_values($map))
                ->update([$column => null]);
        }
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropColumn([
                'movement_type',
                'gender',
                'is_production_year_approximate',
                'case_height_mm',
                'glass_type',
                'bezel_material',
                'bezel_color',
                'water_resistance_bar',
                'dial_numerals',
                'bracelet_color',
                'clasp_type',
                'clasp_material',
                'lug_width_mm',
            ]);
        });
    }
};
