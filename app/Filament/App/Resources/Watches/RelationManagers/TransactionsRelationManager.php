<?php

/**
 * =========================================================================
 * TransactionsRelationManager — Preishistorie direkt an der Uhr
 * =========================================================================
 *
 * Zweck:
 *   Zeigt auf der Uhren-Bearbeitungsseite alle An-/Verkäufe und erlaubt
 *   die Erfassung im Kontext der Uhr. Formular und Tabelle werden aus
 *   der TransactionResource WIEDERVERWENDET (withWatch: false).
 *
 * WARUM CreateAction->using():
 *   Belege verändern die Uhr (Status/purchase_*-Sync) — die Erstellung
 *   MUSS durch RecordSaleAction/RecordPurchaseAction laufen.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches\RelationManagers;

use App\Actions\Transactions\RecordPurchaseAction;
use App\Actions\Transactions\RecordSaleAction;
use App\Enums\TransactionType;
use App\Filament\App\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\App\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use App\Models\Watch;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'An- & Verkäufe';

    public function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema, withWatch: false);
    }

    public function table(Table $table): Table
    {
        return TransactionsTable::configure($table, withWatch: false)
            ->modelLabel('Beleg')
            ->pluralModelLabel('Belege')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->with('contact'))
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Transaction {
                        /** @var Watch $watch */
                        $watch = $this->getOwnerRecord();

                        return $data['type'] === TransactionType::Sale->value
                            ? app(RecordSaleAction::class)->execute($watch, $data)
                            : app(RecordPurchaseAction::class)->execute($watch, $data);
                    }),
            ]);
    }
}
