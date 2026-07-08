<?php

/**
 * =========================================================================
 * RecordPurchaseAction — Ankauf einer Uhr erfassen
 * =========================================================================
 *
 * Zweck:
 *   Legt den Ankauf-Beleg (Transaction, type=purchase) an und hält die
 *   Uhr synchron: purchase_*-Schnellzugriffsfelder werden aktualisiert,
 *   eine zuvor verkaufte Uhr geht zurück in den Bestand (Rückkauf).
 *
 * Aufrufer:
 *   - WatchObserver (created): Uhren, die direkt mit Einkaufsdaten
 *     angelegt werden, bekommen automatisch ihren Ankauf-Beleg
 *     ($syncWatch=false — die Felder stehen dort schon).
 *   - Filament (Ankauf erfassen / TransactionsRelationManager)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Enums\WatchStatus;
use App\Models\Transaction;
use App\Models\Watch;

class RecordPurchaseAction
{
    /**
     * @param  array{contact_id?: string|null, price: float|string, transacted_at: string|\DateTimeInterface, payment_method?: string|null, document_number?: string|null, notes?: string|null}  $data
     */
    public function execute(Watch $watch, array $data, bool $syncWatch = true): Transaction
    {
        $transaction = $watch->transactions()->create([
            'type' => TransactionType::Purchase,
            'contact_id' => $data['contact_id'] ?? null,
            'price' => $data['price'],
            'transacted_at' => $data['transacted_at'],
            'payment_method' => $data['payment_method'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($syncWatch) {
            // Schnellzugriffsfelder aktualisieren; Rückkauf holt die Uhr
            // zurück in den Bestand. saveQuietly: der WatchObserver darf
            // hieraus keinen weiteren Auto-Beleg ableiten.
            $watch->forceFill([
                'purchase_price' => $data['price'],
                'purchase_date' => $data['transacted_at'],
                'status' => $watch->isSold() ? WatchStatus::InStock : $watch->getAttribute('status'),
            ])->saveQuietly();
        }

        return $transaction;
    }
}
