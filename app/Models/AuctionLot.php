<?php

/**
 * =========================================================================
 * AuctionLot — Los einer Auktion (Tenant-Datenbank, Modul 8)
 * =========================================================================
 *
 * Zweck:
 *   Verknüpft eine Uhr mit einer Auktion: Losnummer, Schätzpreis-Spanne,
 *   Limit (reserve_price) und Ergebnis (Zuschlag/Rückgang/Rückzug).
 *   previous_watch_status merkt sich den Uhren-Status vor der
 *   Einlieferung — Rückgang/Rückzug stellt ihn wieder her.
 *
 * Erstellung/Abrechnung IMMER über die Actions (AddLotToAuctionAction /
 * SettleLotAction) — sie halten den Uhren-Status synchron und erzeugen
 * beim Zuschlag den Verkaufsbeleg (RecordSaleAction, Modul 5).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuctionLotStatus;
use App\Enums\WatchStatus;
use Database\Factories\AuctionLotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuctionLot extends Model
{
    /** @use HasFactory<AuctionLotFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'auction_id',
        'watch_id',
        'buyer_contact_id',
        'created_by_user_id',
        'lot_number',
        'status',
        'previous_watch_status',
        'starting_price',
        'estimate_low',
        'estimate_high',
        'reserve_price',
        'hammer_price',
        'currency',
        'settled_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lot_number' => 'integer',
            'status' => AuctionLotStatus::class,
            'previous_watch_status' => WatchStatus::class,
            'starting_price' => 'decimal:2',
            'estimate_low' => 'decimal:2',
            'estimate_high' => 'decimal:2',
            'reserve_price' => 'decimal:2',
            'hammer_price' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * Erfasser automatisch setzen (Tenant-Benutzer).
     */
    protected static function booted(): void
    {
        static::creating(function (AuctionLot $lot): void {
            $lot->created_by_user_id ??= auth()->id();
        });
    }

    /**
     * @return BelongsTo<Auction, $this>
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * @return BelongsTo<Watch, $this>
     */
    public function watch(): BelongsTo
    {
        return $this->belongsTo(Watch::class);
    }

    /**
     * Käufer (bei Zuschlag).
     *
     * @return BelongsTo<Contact, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'buyer_contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Online-Gebote auf dieses Los (Modul 8b), höchste zuerst.
     *
     * @return HasMany<AuctionBid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class)->orderByDesc('amount');
    }

    /**
     * Aktuelles Höchstgebot — null, wenn noch keines vorliegt.
     */
    public function highestBidAmount(): ?float
    {
        $max = $this->bids()->max('amount');

        return $max === null ? null : (float) $max;
    }

    /**
     * Mindestbetrag für das nächste Gebot: Höchstgebot + Erhöhungsschritt,
     * sonst Startpreis (Fallback: untere Schätzung, zuletzt 50 €).
     */
    public function minimumNextBid(): float
    {
        $highest = $this->highestBidAmount();

        if ($highest !== null) {
            return $highest + self::bidIncrementFor($highest);
        }

        $startingPrice = $this->getAttribute('starting_price');

        if ($startingPrice !== null) {
            return (float) $startingPrice;
        }

        $estimateLow = $this->getAttribute('estimate_low');

        return $estimateLow !== null ? (float) $estimateLow : 50.0;
    }

    /**
     * Übliche Auktions-Erhöhungsschritte, gestaffelt nach Gebotshöhe.
     */
    public static function bidIncrementFor(float $amount): float
    {
        return match (true) {
            $amount < 100 => 10.0,
            $amount < 500 => 25.0,
            $amount < 1000 => 50.0,
            $amount < 2000 => 100.0,
            $amount < 5000 => 200.0,
            $amount < 10000 => 500.0,
            $amount < 50000 => 1000.0,
            default => 2500.0,
        };
    }

    /**
     * Noch nicht abgerechnet? (getAttribute — typsicher für PHPStan.)
     */
    public function isOpen(): bool
    {
        return $this->getAttribute('status') === AuctionLotStatus::Open;
    }

    /**
     * Zugeschlagen?
     */
    public function isSold(): bool
    {
        return $this->getAttribute('status') === AuctionLotStatus::Sold;
    }
}
