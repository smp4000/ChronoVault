<?php

/**
 * =========================================================================
 * Migration: watches — Kauf-, Eigentums- und Verwaltungsfelder (Modul 3)
 * =========================================================================
 *
 * Zweck:
 *   Übernahme der bewährten Felder aus der Vorgänger-Anwendung des
 *   Auftraggebers: Kaufdaten, Eigentumsverhältnis (Kommission!),
 *   Versicherung, Funktionen/Komplikationen, Limited Edition, Lagerort,
 *   öffentliche Beschreibung sowie Vorhalte-Spalten für Modul 4
 *   (photo_slots — geführter Foto-Upload) und Modul 7 (Marktbewertung).
 *
 * Abgrenzung:
 *   - purchase_* ist der EINKAUF der aktuell im Bestand liegenden Uhr.
 *     Verkäufe + vollständige Preishistorie werden eigene Tabellen (Modul 5).
 *   - current_market_value/last_valuation_at/watchcharts_uuid werden von
 *     Modul 7 (Bewertungen) gepflegt — hier nur die Spalten.
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
            // Erfasser (Tenant-Benutzer); nullable — Altbestand/Systemimporte.
            $table->foreignId('created_by_user_id')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();

            // Eigentum & Verwaltung
            $table->string('ownership_status')->default('owned')->after('status');
            $table->string('owner_name')->nullable()->after('ownership_status');
            $table->text('owner_address')->nullable()->after('owner_name');
            $table->string('storage_location')->nullable()->after('owner_address');

            // Kauf (Einkauf des aktuellen Bestandsexemplars)
            $table->decimal('purchase_price', 12, 2)->nullable()->after('storage_location');
            $table->date('purchase_date')->nullable()->after('purchase_price');
            $table->string('purchase_location')->nullable()->after('purchase_date');
            $table->text('delivery_scope')->nullable()->after('purchase_location');

            // Funktionen/Komplikationen (JSON-Array aus WatchFunction-Codes)
            $table->json('functions')->nullable()->after('lug_width_mm');

            // Limited Edition
            $table->boolean('is_limited_edition')->default(false)->after('functions');
            $table->unsignedInteger('limited_edition_number')->nullable()->after('is_limited_edition');
            $table->unsignedInteger('limited_edition_total')->nullable()->after('limited_edition_number');

            // Öffentliche Beschreibung (Inserat-Text) — getrennt von internen notes
            $table->text('description')->nullable()->after('notes');

            // Versicherung
            $table->string('insurance_company')->nullable()->after('research_data');
            $table->string('insurance_policy_number')->nullable()->after('insurance_company');
            $table->decimal('insurance_value', 12, 2)->nullable()->after('insurance_policy_number');
            $table->date('insurance_valid_until')->nullable()->after('insurance_value');
            $table->text('insurance_notes')->nullable()->after('insurance_valid_until');

            // Vorhalte-Spalten: Modul 4 (geführter Foto-Upload) & Modul 7 (Bewertung)
            $table->json('photo_slots')->nullable()->after('insurance_notes');
            $table->string('watchcharts_uuid')->nullable()->after('photo_slots');
            $table->decimal('current_market_value', 12, 2)->nullable()->after('watchcharts_uuid');
            $table->timestamp('last_valuation_at')->nullable()->after('current_market_value');
        });

        Schema::table('calibers', function (Blueprint $table) {
            // Grundkaliber (z. B. "ETA 2892-A2" bei modifizierten Werken)
            $table->string('base_caliber')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn([
                'ownership_status',
                'owner_name',
                'owner_address',
                'storage_location',
                'purchase_price',
                'purchase_date',
                'purchase_location',
                'delivery_scope',
                'functions',
                'is_limited_edition',
                'limited_edition_number',
                'limited_edition_total',
                'description',
                'insurance_company',
                'insurance_policy_number',
                'insurance_value',
                'insurance_valid_until',
                'insurance_notes',
                'photo_slots',
                'watchcharts_uuid',
                'current_market_value',
                'last_valuation_at',
            ]);
        });

        Schema::table('calibers', function (Blueprint $table) {
            $table->dropColumn('base_caliber');
        });
    }
};
