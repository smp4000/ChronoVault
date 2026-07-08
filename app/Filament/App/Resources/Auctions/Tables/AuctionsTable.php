<?php

/**
 * =========================================================================
 * AuctionsTable — Tabellen-Definition der Auktionen
 * =========================================================================
 *
 * Zweck:
 *   Übersicht aller Auktionen mit Los-Kennzahlen (Lose gesamt,
 *   Zuschläge, Erlös). Die Kennzahlen kommen aus withCount/withSum —
 *   keine N+1-Queries.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Auctions\Tables;

use App\Enums\AuctionLotStatus;
use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuctionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount([
                    'lots',
                    'lots as sold_lots_count' => fn (Builder $lots): Builder => $lots
                        ->where('status', AuctionLotStatus::Sold->value),
                ])
                ->withSum([
                    'lots as hammer_sum' => fn (Builder $lots): Builder => $lots
                        ->where('status', AuctionLotStatus::Sold->value),
                ], 'hammer_price'))
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->weight('semibold')
                    ->searchable()
                    ->description(fn ($record): ?string => $record->location),

                TextColumn::make('venue')
                    ->label('Form')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('starts_at')
                    ->label('Beginn')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('lots_count')
                    ->label('Lose')
                    ->alignEnd(),

                TextColumn::make('sold_lots_count')
                    ->label('Zuschläge')
                    ->alignEnd(),

                TextColumn::make('hammer_sum')
                    ->label('Erlös')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AuctionStatus::class),

                SelectFilter::make('venue')
                    ->label('Austragungsform')
                    ->options(AuctionVenue::class),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Auktion löschen')
                    ->successNotificationTitle('Auktion gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Auktion wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Auktion endgültig löschen')
                    ->successNotificationTitle('Auktion endgültig gelöscht'),
            ])
            ->emptyStateHeading('Keine Auktionen')
            ->emptyStateDescription('Legen Sie eine Auktion an und liefern Sie Uhren als Lose ein.')
            ->emptyStateIcon('heroicon-o-megaphone');
    }
}
