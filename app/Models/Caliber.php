<?php

/**
 * =========================================================================
 * Caliber — Uhrwerk/Kaliber (Stammdaten, Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Stammdatensatz eines Uhrwerks (z. B. "Rolex 3235", "ETA 2824-2").
 *   Gehört immer zu einer Brand (dem Hersteller des Werks). Wird ab
 *   Modul 3 von Uhren referenziert.
 *
 * Verantwortlichkeiten:
 *   - Technische Kenndaten (Werktyp, Gangreserve, Frequenz, Steine, Ø)
 *   - Beziehung zum Hersteller (belongsTo Brand)
 *
 * Einheiten (Spaltennamen tragen die Einheit — selbstdokumentierend):
 *   - power_reserve_hours : Gangreserve in Stunden
 *   - frequency_vph       : Halbschwingungen pro Stunde (z. B. 28800)
 *   - diameter_mm         : Werkdurchmesser in Millimetern
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\CaliberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caliber extends Model
{
    /** @use HasFactory<CaliberFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'name',
        'base_caliber',
        'movement_type',
        'power_reserve_hours',
        'frequency_vph',
        'jewels',
        'diameter_mm',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'power_reserve_hours' => 'integer',
            'frequency_vph' => 'integer',
            'jewels' => 'integer',
            'diameter_mm' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Hersteller dieses Werks (Marke ODER Werkhersteller wie ETA).
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Uhren mit diesem Werk im Bestand (Modul 3).
     *
     * @return HasMany<Watch, $this>
     */
    public function watches(): HasMany
    {
        return $this->hasMany(Watch::class);
    }
}
