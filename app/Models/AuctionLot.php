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
        'lot_code',
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
     * Erfasser und Los-Code automatisch setzen.
     */
    protected static function booted(): void
    {
        static::creating(function (AuctionLot $lot): void {
            $lot->created_by_user_id ??= auth()->id();
            $lot->lot_code ??= self::generateLotCode();
        });
    }

    /**
     * Eindeutiger öffentlicher Los-Code: 6 GROSSBUCHSTABEN (A–Z),
     * kollisionsgeprüft inkl. soft-gelöschter Lose (Unique-Index!).
     */
    public static function generateLotCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= chr(random_int(65, 90));
            }
        } while (self::withTrashed()->where('lot_code', $code)->exists());

        return $code;
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
     * Mindest-Erhöhungsschritt des Loses — pro AUKTION einstellbar
     * (auctions.bid_increment, Standard 100 €). Der Bieter wählt seinen
     * Betrag frei, muss das Höchstgebot aber um mindestens diesen
     * Schritt übertreffen (bewusst KEINE Staffel).
     */
    public function bidIncrement(): float
    {
        $increment = $this->auction?->getAttribute('bid_increment');

        return $increment !== null ? (float) $increment : 100.0;
    }

    /**
     * Mindestbetrag für das nächste Gebot: Höchstgebot + Schritt,
     * sonst Startpreis (Fallback: untere Schätzung, zuletzt 50 €).
     */
    public function minimumNextBid(): float
    {
        $highest = $this->highestBidAmount();

        if ($highest !== null) {
            return $highest + $this->bidIncrement();
        }

        $startingPrice = $this->getAttribute('starting_price');

        if ($startingPrice !== null) {
            return (float) $startingPrice;
        }

        $estimateLow = $this->getAttribute('estimate_low');

        return $estimateLow !== null ? (float) $estimateLow : 50.0;
    }

    /**
     * Ist das Limit (reserve_price) mit dem aktuellen Höchstgebot
     * erreicht? true auch ohne Limit. Das Limit selbst bleibt intern!
     */
    public function isReserveMet(): bool
    {
        $reserve = $this->getAttribute('reserve_price');

        if ($reserve === null) {
            return true;
        }

        $highest = $this->highestBidAmount();

        return $highest !== null && $highest >= (float) $reserve;
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
