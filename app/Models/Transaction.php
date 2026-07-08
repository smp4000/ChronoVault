<?php

/**
 * =========================================================================
 * Transaction — Kauf-/Verkaufsvorgang einer Uhr (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Ein An- oder Verkauf (TransactionType) einer Uhr — zusammen bilden
 *   die Transaktionen die PREISHISTORIE. Belege sind Ewigkeitsdaten:
 *   SoftDeletes, restrictOnDelete auf Uhr und Kontakt, Policies
 *   verhindern das Löschen referenzierter Bezüge.
 *
 * Erstellung IMMER über die Actions (RecordPurchaseAction /
 * RecordSaleAction) — sie halten den Uhren-Status und die
 * purchase_*-Schnellzugriffsfelder synchron.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'watch_id',
        'contact_id',
        'created_by_user_id',
        'type',
        'price',
        'currency',
        'transacted_at',
        'payment_method',
        'document_number',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'price' => 'decimal:2',
            'transacted_at' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /**
     * Erfasser automatisch setzen (Tenant-Benutzer).
     */
    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            $transaction->created_by_user_id ??= auth()->id();
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
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
