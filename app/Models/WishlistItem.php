<?php

/**
 * =========================================================================
 * WishlistItem — Wunschmodell des Sammlers (Tenant)
 * =========================================================================
 *
 * Zweck:
 *   Uhr, die der Sammler/Händler noch NICHT besitzt, aber beobachtet:
 *   Marke/Modell/Referenz + Zielpreis. Die nächtliche Wertermittlung
 *   (wishlist:update-values) pflegt current_market_value; bei
 *   Zielpreis-Erreichen geht die WishlistPriceAlertMail raus
 *   (notified_at = Spam-Schutz, Re-Arm bei Preisen über Ziel).
 *
 * Abhängigkeiten: belongsTo Brand; WishlistStatus-Enum.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\WishlistStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WishlistItem extends Model
{
    use HasUuids;
    use SoftDeletes;

    /**
     * Status-Default auch am Model-Objekt (nicht nur DB-Default).
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'brand_id',
        'model_name',
        'reference_number',
        'target_price',
        'status',
        'current_market_value',
        'value_low',
        'value_high',
        'last_valuation_at',
        'notified_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_price' => 'decimal:2',
            'current_market_value' => 'decimal:2',
            'value_low' => 'decimal:2',
            'value_high' => 'decimal:2',
            'last_valuation_at' => 'datetime',
            'notified_at' => 'datetime',
            'status' => WishlistStatus::class,
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
     * Anzeigename: Marke + Modell (+ Referenz).
     */
    public function displayName(): string
    {
        $name = $this->brand->name.' '.$this->model_name;

        return $this->reference_number !== null
            ? $name.' ('.$this->reference_number.')'
            : $name;
    }

    /**
     * Zielpreis erreicht? (Marktwert liegt auf/unter dem Ziel)
     */
    public function isTargetReached(): bool
    {
        return $this->target_price !== null
            && $this->current_market_value !== null
            && (float) $this->current_market_value <= (float) $this->target_price;
    }
}
