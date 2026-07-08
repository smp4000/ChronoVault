<?php

/**
 * =========================================================================
 * CreateTransaction — Beleg-Erstellung über die Domain-Actions
 * =========================================================================
 *
 * WARUM handleRecordCreation überschrieben:
 *   Ein Beleg verändert die Uhr (Status Verkauft bzw. purchase_*-Sync).
 *   Diese Logik lebt in RecordSaleAction/RecordPurchaseAction — die Page
 *   leitet nur dorthin (keine Business-Logik in Filament, Projektregel).
 * =========================================================================
 */

namespace App\Filament\App\Resources\Transactions\Pages;

use App\Actions\Transactions\RecordPurchaseAction;
use App\Actions\Transactions\RecordSaleAction;
use App\Enums\TransactionType;
use App\Filament\App\Resources\Transactions\TransactionResource;
use App\Models\Watch;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $watch = Watch::findOrFail($data['watch_id']);

        return $data['type'] === TransactionType::Sale->value
            ? app(RecordSaleAction::class)->execute($watch, $data)
            : app(RecordPurchaseAction::class)->execute($watch, $data);
    }
}
