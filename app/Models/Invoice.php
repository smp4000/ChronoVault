<?php

/**
 * =========================================================================
 * Invoice — Rechnung zu einem Verkaufsbeleg (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Rechnung mit lückenlosem Nummernkreis und eingefrorenem
 *   Daten-Snapshot (seller/buyer/line). PDFs (klassisch + ZUGFeRD-
 *   E-Rechnung + Kaufvertrag) rendert der InvoiceService AUS DIESEM
 *   Snapshot — nie aus den Live-Daten.
 *
 * Erstellung IMMER über InvoiceService::getOrCreateForSale()
 * (Nummernvergabe unter DB-Sperre, Pflichtangaben-Guards).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'created_by_user_id',
        'invoice_number',
        'issued_at',
        'delivery_date',
        'tax_mode',
        'net_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'seller',
        'buyer',
        'line',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'delivery_date' => 'date',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'seller' => 'array',
            'buyer' => 'array',
            'line' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            $invoice->created_by_user_id ??= auth()->id();
        });
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Typsichere Snapshot-Zugriffe (JSON-Casts sind für die statische
     * Analyse als string typisiert — etabliertes Larastan-Muster).
     *
     * @return array<string, mixed>
     */
    public function sellerData(): array
    {
        return (array) $this->getAttribute('seller');
    }

    /**
     * @return array<string, mixed>
     */
    public function buyerData(): array
    {
        return (array) $this->getAttribute('buyer');
    }

    /**
     * @return array<string, mixed>
     */
    public function lineData(): array
    {
        return (array) $this->getAttribute('line');
    }

    public function issuedAtDate(): Carbon
    {
        $value = $this->getAttribute('issued_at');

        return $value instanceof Carbon ? $value : Carbon::parse((string) $value);
    }

    public function deliveryDateDate(): ?Carbon
    {
        $value = $this->getAttribute('delivery_date');

        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse((string) $value);
    }
}
