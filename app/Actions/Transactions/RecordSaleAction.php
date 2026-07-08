<?php

/**
 * =========================================================================
 * RecordSaleAction — Verkauf einer Uhr erfassen
 * =========================================================================
 *
 * Zweck:
 *   Legt den Verkaufs-Beleg (Transaction, type=sale) an und setzt die
 *   Uhr auf Status "Verkauft". Liefert zusätzlich die Marge gegenüber
 *   dem hinterlegten Einkaufspreis (für die UI-Notification).
 *
 * Aufrufer:
 *   - Filament "Verkaufen"-Action (Bestandsliste / Uhr bearbeiten)
 *   - TransactionsRelationManager
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Enums\WatchStatus;
use App\Models\Transaction;
use App\Models\Watch;

class RecordSaleAction
{
    /**
     * @param  array{contact_id?: string|null, price: float|string, transacted_at: string|\DateTimeInterface, payment_method?: string|null, document_number?: string|null, notes?: string|null}  $data
     */
    public function execute(Watch $watch, array $data): Transaction
    {
        $transaction = $watch->transactions()->create([
            'type' => TransactionType::Sale,
            'contact_id' => $data['contact_id'] ?? null,
            'price' => $data['price'],
            'transacted_at' => $data['transacted_at'],
            'payment_method' => $data['payment_method'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // saveQuietly: kein erneuter Observer-Durchlauf nötig.
        $watch->forceFill(['status' => WatchStatus::Sold])->saveQuietly();

        return $transaction;
    }

    /**
     * Marge des Verkaufs gegenüber dem Einkaufspreis der Uhr —
     * null, wenn kein Einkaufspreis hinterlegt ist.
     */
    public function margin(Watch $watch, float $salePrice): ?float
    {
        if ($watch->purchase_price === null) {
            return null;
        }

        return round($salePrice - (float) $watch->purchase_price, 2);
    }
}
