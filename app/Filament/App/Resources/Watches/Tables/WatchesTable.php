<?php

/**
 * =========================================================================
 * WatchesTable — Tabellen-Definition des Uhrenbestands (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Bestandsliste mit Status-/Zustands-Badges (deutsche Labels aus den
 *   Enums), Full-Set-Indikator und Papierkorb-Filter. Autorisierung
 *   übernimmt die WatchPolicy — hier steht KEINE eigene Logik.
 *
 * WARUM keine Bulk-Löschaktion:
 *   Konsistenz mit allen bisherigen Tabellen (Policy-Checks pro Datensatz).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches\Tables;

use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Models\Watch;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager Loading der Marke — verhindert N+1 in der Spalte.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('brand'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('')
                    ->collection('photos')
                    ->limit(1)
                    ->imageSize(40)
                    ->extraImgAttributes(['style' => 'border-radius: 0.5rem; object-fit: cover;'])
                    ->toggleable(),

                TextColumn::make('stock_number')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('brand.name')
                    ->label('Marke')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('model_name')
                    ->label('Modell')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Watch $record): ?string => $record->reference_number),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('condition')
                    ->label('Zustand')
                    ->badge(),

                IconColumn::make('has_box')
                    ->label('Box')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('has_papers')
                    ->label('Papiere')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('production_year')
                    ->label('Baujahr')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('serial_number')
                    ->label('Seriennummer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Angelegt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Marke')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(WatchStatus::class)
                    ->multiple(),

                SelectFilter::make('condition')
                    ->label('Zustand')
                    ->options(WatchCondition::class)
                    ->multiple(),

                TernaryFilter::make('full_set')
                    ->label('Full Set')
                    ->placeholder('Alle Uhren')
                    ->trueLabel('Nur Full Set (Box & Papiere)')
                    ->falseLabel('Ohne vollständiges Set')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('has_box', true)->where('has_papers', true),
                        false: fn (Builder $query): Builder => $query->where(
                            fn (Builder $q): Builder => $q->where('has_box', false)->orWhere('has_papers', false)
                        ),
                    ),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Uhr löschen')
                    ->successNotificationTitle('Uhr gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Uhr wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Uhr endgültig löschen')
                    ->successNotificationTitle('Uhr endgültig gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Uhren im Bestand')
            ->emptyStateDescription('Erfassen Sie Ihre erste Uhr — Marke und Kaliber stammen aus den Stammdaten.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
