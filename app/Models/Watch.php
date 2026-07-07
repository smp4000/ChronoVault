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
use App\Enums\OwnershipStatus;
use App\Enums\WatchColor;
use App\Enums\WatchCondition;
use App\Enums\WatchGender;
use App\Enums\WatchStatus;
use App\Observers\WatchObserver;
use Database\Factories\WatchFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[ObservedBy([WatchObserver::class])]
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

    /**
     * Model-seitige Defaults — der DB-Default greift nur in der Datenbank,
     * nicht am frisch erstellten Model-Objekt.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ownership_status' => 'owned',
    ];

    protected $fillable = [
        'created_by_user_id',
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
        'functions',
        'ownership_status',
        'owner_name',
        'owner_address',
        'storage_location',
        'purchase_price',
        'purchase_date',
        'purchase_location',
        'delivery_scope',
        'is_limited_edition',
        'limited_edition_number',
        'limited_edition_total',
        'description',
        'notes',
        'research_data',
        'insurance_company',
        'insurance_policy_number',
        'insurance_value',
        'insurance_valid_until',
        'insurance_notes',
        'photo_slots',
        'photos',
        'watchcharts_uuid',
        'current_market_value',
        'last_valuation_at',
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
            // Funktionen als Array von WatchFunction-Codes (Mehrfachauswahl)
            'functions' => 'array',
            'ownership_status' => OwnershipStatus::class,
            'purchase_price' => 'decimal:2',
            'purchase_date' => 'date',
            'is_limited_edition' => 'boolean',
            'limited_edition_number' => 'integer',
            'limited_edition_total' => 'integer',
            'insurance_value' => 'decimal:2',
            'insurance_valid_until' => 'date',
            // Modul 7 (Bewertungen) pflegt diese Felder
            'current_market_value' => 'decimal:2',
            'last_valuation_at' => 'datetime',
            // Modul 4 (geführter Foto-Upload) nutzt diese Slots
            'photo_slots' => 'array',
            // Gespeicherte Fotos (Pfade auf der tenant-isolierten public-Disk)
            'photos' => 'array',
            // KI-Rechercheergebnis (Beschreibung, Bild-/Quellen-URLs) —
            // Bild-Übernahme in die Media Library folgt in Modul 4.
            'research_data' => 'array',
        ];
    }

    /**
     * Erfasser automatisch setzen (Tenant-Benutzer) — analog zum
     * user_id-Feld der Vorgänger-Anwendung, aber nur dokumentierend.
     */
    protected static function booted(): void
    {
        static::creating(function (Watch $watch): void {
            $watch->created_by_user_id ??= auth()->id();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
     * Öffentliche URLs der gespeicherten Fotos (stancl-Asset-Route,
     * tenant-isoliert). Leeres Array, wenn keine Fotos vorliegen.
     *
     * @return array<int, string>
     */
    public function photoUrls(): array
    {
        return array_map(
            fn (string $path): string => tenant_asset($path),
            $this->photos ?? [],
        );
    }

    /**
     * URL des ersten Fotos — für Listen-Thumbnails.
     */
    public function firstPhotoUrl(): ?string
    {
        return $this->photoUrls()[0] ?? null;
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
