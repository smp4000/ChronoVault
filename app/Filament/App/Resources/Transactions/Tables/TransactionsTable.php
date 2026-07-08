<?php

/**
 * =========================================================================
 * TransactionsTable — Tabellen-Definition der Kauf-/Verkaufsbelege
 * =========================================================================
 *
 * Zweck:
 *   Wird von der TransactionResource UND dem TransactionsRelationManager
 *   genutzt — `withWatch: false` blendet die Uhren-Spalte aus (im
 *   Relation Manager ist die Uhr der Kontext).
 *
 * WARUM keine Bulk-Löschaktion: Konsistenz (Policy-Checks pro Datensatz).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Transactions\Tables;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table, bool $withWatch = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $withWatch
                ? $query->with(['watch.brand', 'contact'])
                : $query->with('contact'))
            ->columns(array_filter([
                TextColumn::make('transacted_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Art')
                    ->badge(),

                $withWatch
                    ? TextColumn::make('watch.model_name')
                        ->label('Uhr')
                        ->state(fn (Transaction $record): string => $record->watch->fullName())
                        ->searchable(query: fn (Builder $query, string $search): Builder => $query
                            ->whereHas('watch', fn (Builder $q) => $q
                                ->where('model_name', 'like', "%{$search}%")
                                ->orWhere('reference_number', 'like', "%{$search}%")))
                        ->weight('semibold')
                    : null,

                TextColumn::make('contact.last_name')
                    ->label('Kontakt')
                    ->state(fn (Transaction $record): string => $record->contact?->displayName() ?? '—'),

                TextColumn::make('price')
                    ->label('Preis')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('payment_method')
                    ->label('Zahlungsart')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('document_number')
                    ->label('Belegnr.')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
            ]))
            ->defaultSort('transacted_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Art')
                    ->options(TransactionType::class),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Beleg stornieren (Papierkorb)')
                    ->successNotificationTitle('Beleg storniert'),

                RestoreAction::make()
                    ->successNotificationTitle('Beleg wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Beleg endgültig löschen')
                    ->successNotificationTitle('Beleg endgültig gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Belege')
            ->emptyStateDescription('An- und Verkäufe erscheinen hier als Preishistorie.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
