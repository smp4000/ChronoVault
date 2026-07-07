<?php

/**
 * =========================================================================
 * Brand — Uhrenmarke (Stammdaten, Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Stammdatensatz einer Uhrenmarke bzw. eines Werkherstellers
 *   (Rolex, Omega, aber auch ETA/Sellita). Liegt in der TENANT-Datenbank —
 *   jeder Mandant pflegt seinen eigenen Markenkatalog (ADR-009).
 *
 * Verantwortlichkeiten:
 *   - Beziehung zu Kalibern (hasMany)
 *   - UUID-Primärschlüssel + SoftDeletes (Domänenentität, wird ab Modul 3
 *     von Uhren referenziert)
 *
 * WARUM auch Werkhersteller als Brand:
 *   Kaliber wie das ETA 2824-2 stammen von Werkherstellern, die selbst
 *   keine (relevanten) Uhren bauen. Ein eigener Entitätstyp dafür wäre
 *   Overengineering — die is_active-Flagge und spätere Auswertungen
 *   unterscheiden ausreichend.
 *
 * Mögliche Erweiterungen:
 *   - Logo (Modul 4 Medienverwaltung), Scout-Searchable (Modul 3)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'founded_year',
        'website',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kaliber dieser Marke bzw. dieses Werkherstellers.
     *
     * @return HasMany<Caliber, $this>
     */
    public function calibers(): HasMany
    {
        return $this->hasMany(Caliber::class);
    }
}
