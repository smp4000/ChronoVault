<?php

/**
 * =========================================================================
 * TransactionResource — Kauf-/Verkaufsbelege im Tenant-Panel (Modul 5)
 * =========================================================================
 *
 * Zweck:
 *   Übersicht und Erfassung aller An-/Verkäufe (Preishistorie).
 *   Zugriff regelt App\Policies\TransactionPolicy (transactions.*).
 *
 * WICHTIG:
 *   Die Erstellung läuft über RecordPurchaseAction/RecordSaleAction
 *   (CreateTransaction-Page bzw. RelationManager) — sie halten den
 *   Uhren-Status und die purchase_*-Felder synchron. Formular/Tabelle
 *   werden vom TransactionsRelationManager (WatchResource)
 *   wiederverwendet (withWatch: false).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Transactions;

use App\Filament\App\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\App\Resources\Transactions\Pages\EditTransaction;
use App\Filament\App\Resources\Transactions\Pages\ListTransactions;
use App\Filament\App\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\App\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Beleg';

    protected static ?string $pluralModelLabel = 'An- & Verkäufe';

    protected static string|\UnitEnum|null $navigationGroup = 'Verkauf';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
