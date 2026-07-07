<?php

/**
 * =========================================================================
 * CalibersTable — Tabellen-Definition der Kaliber-Stammdaten (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Kaliberliste mit Werktyp-Badge (deutsche Labels aus MovementType),
 *   technischen Kenndaten und Papierkorb-Filter. Wird von der
 *   CaliberResource UND dem CalibersRelationManager genutzt —
 *   `withBrand: false` blendet Hersteller-Spalte und -Filter aus
 *   (im Relation Manager ist der Hersteller der Kontext).
 *
 * WARUM keine Bulk-Löschaktion:
 *   Konsistenz mit UsersTable/BrandsTable — Policy-Checks pro Datensatz.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Calibers\Tables;

use App\Enums\MovementType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CalibersTable
{
    public static function configure(Table $table, bool $withBrand = true): Table
    {
        return $table
            // Eager Loading des Herstellers — verhindert N+1 in der Spalte.
            ->modifyQueryUsing(fn (Builder $query): Builder => $withBrand ? $query->with('brand') : $query)
            ->columns(array_filter([
                TextColumn::make('name')
                    ->label('Bezeichnung')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                $withBrand
                    ? TextColumn::make('brand.name')
                        ->label('Hersteller')
                        ->searchable()
                        ->sortable()
                    : null,

                TextColumn::make('movement_type')
                    ->label('Werktyp')
                    ->badge(),

                TextColumn::make('power_reserve_hours')
                    ->label('Gangreserve')
                    ->formatStateUsing(fn (?int $state): ?string => $state !== null ? $state.' Std.' : null)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('frequency_vph')
                    ->label('Frequenz (A/h)')
                    ->numeric()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('jewels')
                    ->label('Steine')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Angelegt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]))
            ->defaultSort('name')
            ->filters(array_filter([
                $withBrand
                    ? SelectFilter::make('brand_id')
                        ->label('Hersteller')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload()
                    : null,

                SelectFilter::make('movement_type')
                    ->label('Werktyp')
                    ->options(MovementType::class),

                TernaryFilter::make('is_active')
                    ->label('Aktiv-Status')
                    ->placeholder('Alle Kaliber')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ]))
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Kaliber löschen')
                    ->successNotificationTitle('Kaliber gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Kaliber wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Kaliber endgültig löschen')
                    ->successNotificationTitle('Kaliber endgültig gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Kaliber')
            ->emptyStateDescription('Erfassen Sie Uhrwerke mit ihren technischen Kenndaten.')
            ->emptyStateIcon('heroicon-o-cog-6-tooth');
    }
}
