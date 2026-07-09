<?php

/**
 * =========================================================================
 * PriceProposal — Preisvorschlag eines Shop-Besuchers (Tenant)
 * =========================================================================
 *
 * Zweck:
 *   Persistiert jeden Preisvorschlag von der Shop-Detailseite (zusätzlich
 *   zur Mail an den Händler). Der Händler bearbeitet die Vorschläge im
 *   Panel (annehmen/ablehnen, per Mail antworten).
 *
 * Abhängigkeiten: belongsTo Watch; Status-Enum PriceProposalStatus.
 * Soft Deletes (Domänendaten-Regel), UUID als Primärschlüssel.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceProposalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceProposal extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'watch_id',
        'name',
        'email',
        'proposed_price',
        'asking_price_at_time',
        'message',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_price' => 'decimal:2',
            'asking_price_at_time' => 'decimal:2',
            'status' => PriceProposalStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Watch, $this>
     */
    public function watch(): BelongsTo
    {
        return $this->belongsTo(Watch::class);
    }
}
