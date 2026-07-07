<?php

/**
 * =========================================================================
 * Watch — Uhr (Kernentität, Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Repräsentiert eine physische Uhr im Bestand eines Betriebs — DAS
 *   Kernobjekt der Plattform. Referenziert die Stammdaten aus Modul 2
 *   (Brand Pflicht, Caliber optional).
 *
 * Verantwortlichkeiten:
 *   - Beziehungen zu Brand/Caliber
 *   - Volltextsuche via Laravel Scout (database-Driver lokal, ADR-003 —
 *     der Umstieg auf Meilisearch ist nur ein Driver-Wechsel)
 *   - Anzeige-Helfer fullName() für Notifications/Global Search
 *
 * WARUM keine Preisfelder:
 *   Einkauf/Verkauf/Preishistorie werden eigene Tabellen (Modul 5) —
 *   eine Uhr kann mehrfach den Besitzer wechseln (An- und Wiederverkauf).
 *
 * Mögliche Erweiterungen:
 *   - Fotos/Zertifikate (Modul 4, spatie/laravel-medialibrary)
 *   - Kauf-/Verkaufsbeziehungen (Modul 5), Servicehistorie (Modul 6)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BraceletMaterial;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\WatchColor;
use App\Enums\WatchCondition;
use App\Enums\WatchGender;
use App\Enums\WatchStatus;
use Database\Factories\WatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Watch extends Model
{
    /** @use HasFactory<WatchFactory> */
    use HasFactory;

    use HasUuids;
    use Searchable;
    use SoftDeletes;

    /**
     * Explizit, weil Laravel sonst "watches" korrekt rät — aber die
     * Kernentität der Plattform verdient keine Implizitheit.
     */
    protected $table = 'watches';

    protected $fillable = [
        'brand_id',
        'caliber_id',
        'movement_type',
        'model_name',
        'reference_number',
        'serial_number',
        'stock_number',
        'production_year',
        'is_production_year_approximate',
        'gender',
        'condition',
        'status',
        'has_box',
        'has_papers',
        'case_material',
        'case_diameter_mm',
        'case_height_mm',
        'glass_type',
        'bezel_material',
        'bezel_color',
        'water_resistance_bar',
        'dial_color',
        'dial_numerals',
        'bracelet_material',
        'bracelet_color',
        'clasp_type',
        'clasp_material',
        'lug_width_mm',
        'notes',
        'research_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_year' => 'integer',
            'is_production_year_approximate' => 'boolean',
            'condition' => WatchCondition::class,
            'status' => WatchStatus::class,
            'movement_type' => MovementType::class,
            'gender' => WatchGender::class,
            'has_box' => 'boolean',
            'has_papers' => 'boolean',
            'case_material' => CaseMaterial::class,
            'case_diameter_mm' => 'decimal:1',
            'case_height_mm' => 'decimal:1',
            'glass_type' => GlassType::class,
            'bezel_material' => CaseMaterial::class,
            'bezel_color' => WatchColor::class,
            'water_resistance_bar' => 'integer',
            'dial_color' => WatchColor::class,
            'dial_numerals' => DialNumerals::class,
            'bracelet_material' => BraceletMaterial::class,
            'bracelet_color' => WatchColor::class,
            'clasp_type' => ClaspType::class,
            'clasp_material' => CaseMaterial::class,
            'lug_width_mm' => 'integer',
            // KI-Rechercheergebnis (Beschreibung, Bild-/Quellen-URLs) —
            // Bild-Übernahme in die Media Library folgt in Modul 4.
            'research_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Caliber, $this>
     */
    public function caliber(): BelongsTo
    {
        return $this->belongsTo(Caliber::class);
    }

    /**
     * Anzeige-Name "Marke Modell (Referenz)" — z. B. für Global Search,
     * Notifications und spätere Belege.
     */
    public function fullName(): string
    {
        // brand_id ist Pflichtfeld — die Beziehung existiert immer.
        $name = trim($this->brand->name.' '.$this->model_name);

        return $this->reference_number !== null
            ? $name.' ('.$this->reference_number.')'
            : $name;
    }

    /**
     * Scout-Suchindex (database-Driver: LIKE-Suche über diese Felder).
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'model_name' => $this->model_name,
            'reference_number' => $this->reference_number,
            'serial_number' => $this->serial_number,
            'stock_number' => $this->stock_number,
        ];
    }
}
