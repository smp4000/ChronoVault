<?php

/**
 * =========================================================================
 * Valuation — Marktwert-Bewertung einer Uhr (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Ein Bewertungs-Zeitpunkt (Wert + optionale Spanne + Quellen) —
 *   zusammen die WERTENTWICKLUNG der Uhr. Erstellung IMMER über die
 *   RecordValuationAction, die den Schnellzugriff an der Uhr
 *   (current_market_value/last_valuation_at) mitpflegt.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValuationSource;
use Database\Factories\ValuationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Valuation extends Model
{
    /** @use HasFactory<ValuationFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'watch_id',
        'created_by_user_id',
        'source',
        'market_value',
        'value_low',
        'value_high',
        'currency',
        'valued_at',
        'summary',
        'source_urls',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ValuationSource::class,
            'market_value' => 'decimal:2',
            'value_low' => 'decimal:2',
            'value_high' => 'decimal:2',
            'valued_at' => 'date',
            'source_urls' => 'array',
        ];
    }

    /**
     * Erfasser automatisch setzen (Tenant-Benutzer).
     */
    protected static function booted(): void
    {
        static::creating(function (Valuation $valuation): void {
            $valuation->created_by_user_id ??= auth()->id();
        });
    }

    /**
     * @return BelongsTo<Watch, $this>
     */
    public function watch(): BelongsTo
    {
        return $this->belongsTo(Watch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
