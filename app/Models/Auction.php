<?php

/**
 * =========================================================================
 * Auction — Auktions-Ereignis (Tenant-Datenbank, Modul 8)
 * =========================================================================
 *
 * Zweck:
 *   Eine Versteigerung (Saal/Online/Hybrid) mit Titel, Termin und
 *   Status-Lebenszyklus. Die eigentlichen Objekte hängen als Lose
 *   (AuctionLot) daran — jedes Los verweist auf eine Uhr.
 *
 * Verantwortlichkeiten:
 *   - Beziehung zu den Losen (sortiert nach Losnummer)
 *   - Statuslogik: acceptsLots() (Einlieferung), isCompleted()
 *
 * Lose IMMER über die Actions verwalten (AddLotToAuctionAction /
 * SettleLotAction) — sie halten den Uhren-Status synchron.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuctionLotStatus;
use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'created_by_user_id',
        'title',
        'description',
        'venue',
        'location',
        'status',
        'starts_at',
        'ends_at',
        'currency',
        'notes',
    ];

    /**
     * Model-seitige Defaults (der DB-Default greift nur in der Datenbank).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'venue' => 'saleroom',
        'currency' => 'EUR',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'venue' => AuctionVenue::class,
            'status' => AuctionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Erfasser automatisch setzen (Tenant-Benutzer).
     */
    protected static function booted(): void
    {
        static::creating(function (Auction $auction): void {
            $auction->created_by_user_id ??= auth()->id();
        });
    }

    /**
     * Lose der Auktion in Katalog-Reihenfolge.
     *
     * @return HasMany<AuctionLot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(AuctionLot::class)->orderBy('lot_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Dürfen aktuell Lose eingeliefert werden?
     * (getAttribute — typsicher für statische Analyse.)
     */
    public function acceptsLots(): bool
    {
        return in_array($this->getAttribute('status'), AuctionStatus::acceptingLots(), true);
    }

    /**
     * Nimmt diese Auktion überhaupt Online-Gebote entgegen?
     * (Nur Online-/Hybrid-Auktionen — Saalauktionen laufen vor Ort.)
     */
    public function allowsOnlineBidding(): bool
    {
        return in_array(
            $this->getAttribute('venue'),
            [AuctionVenue::Online, AuctionVenue::Hybrid],
            true,
        );
    }

    /**
     * Ist das Bietfenster gerade offen? Online-fähig + Status "Läuft" +
     * Endzeit (falls gesetzt) noch nicht überschritten.
     */
    public function isBiddingOpen(): bool
    {
        if (! $this->allowsOnlineBidding()) {
            return false;
        }

        if ($this->getAttribute('status') !== AuctionStatus::Live) {
            return false;
        }

        $endsAt = $this->getAttribute('ends_at');

        return ! ($endsAt instanceof Carbon && now()->gt($endsAt));
    }

    /**
     * Ist die Auktion abgeschlossen?
     */
    public function isCompleted(): bool
    {
        return $this->getAttribute('status') === AuctionStatus::Completed;
    }

    /**
     * Automatischer Start: Geplante Auktion, deren Startzeit erreicht
     * ist, wird auf "Läuft" gesetzt. Wird beim öffentlichen Aufruf und
     * vom Scheduler (auctions:start-due) aufgerufen — so startet die
     * Auktion pünktlich, auch ohne dass jemand im Panel klickt.
     */
    public function startIfDue(): bool
    {
        $startsAt = $this->getAttribute('starts_at');

        if ($this->getAttribute('status') !== AuctionStatus::Scheduled
            || ! $startsAt instanceof Carbon
            || $startsAt->isFuture()) {
            return false;
        }

        $this->forceFill(['status' => AuctionStatus::Live])->saveQuietly();

        return true;
    }

    /**
     * Automatischer Abschluss: Sobald das letzte offene Los abgerechnet
     * ist (Zuschlag/Rückgang/Rückzug), gilt die laufende Auktion als
     * beendet. Aufgerufen von der SettleLotAction.
     */
    public function completeIfFullySettled(): bool
    {
        if ($this->getAttribute('status') !== AuctionStatus::Live) {
            return false;
        }

        if ($this->openLotsCount() > 0) {
            return false;
        }

        $this->forceFill(['status' => AuctionStatus::Completed])->saveQuietly();

        return true;
    }

    /**
     * Anzahl noch offener (nicht abgerechneter) Lose — z. B. als Guard
     * für den Abschluss der Auktion.
     */
    public function openLotsCount(): int
    {
        return $this->lots()->where('status', AuctionLotStatus::Open->value)->count();
    }
}
