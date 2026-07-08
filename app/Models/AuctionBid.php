<?php

/**
 * =========================================================================
 * AuctionBid — Online-Gebot auf ein Auktionslos (Tenant-DB, Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Ein Gebot aus dem öffentlichen Auktionskatalog. Bieter sind KEINE
 *   Panel-Benutzer — leichtgewichtige Identität per Name + E-Mail.
 *   Das Höchstgebot eines Loses ist schlicht max(amount).
 *
 * Gebote IMMER über die PlaceBidAction anlegen — sie erzwingt
 * Bietfenster, Mindestgebot und Race-Schutz (DB-Transaktion).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionBid extends Model
{
    use HasUuids;

    protected $fillable = [
        'auction_lot_id',
        'bidder_name',
        'bidder_email',
        'bidder_phone',
        'amount',
        'currency',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AuctionLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'auction_lot_id');
    }
}
