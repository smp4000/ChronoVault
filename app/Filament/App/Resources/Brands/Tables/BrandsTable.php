<?php

/**
 * =========================================================================
 * BrandsTable — Tabellen-Definition der Marken-Stammdaten (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Markenliste mit Kaliber-Anzahl, Aktiv-Status und Papierkorb-Filter
 *   (SoftDeletes). Lösch-Autorisierung inkl. Referenz-Schutz (Marke mit
 *   Kalibern) übernimmt die BrandPolicy — hier steht KEINE eigene Logik.
 *
 * WARUM keine Bulk-Löschaktion:
 *   Wie bei UsersTable — Filament prüft Policies bei Bulk-Aktionen nicht
 *   pro Datensatz; der Referenz-Schutz der Policy würde umgangen.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Brands\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('country')
                    ->label('Land')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('founded_year')
                    ->label('Gegründet')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('calibers_count')
                    ->label('Kaliber')
                    ->counts('calibers')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Angelegt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktiv-Status')
                    ->placeholder('Alle Marken')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Marke löschen')
                    ->successNotificationTitle('Marke gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Marke wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Marke endgültig löschen')
                    ->successNotificationTitle('Marke endgültig gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Marken')
            ->emptyStateDescription('Legen Sie Uhrenmarken und Werkhersteller als Stammdaten an.')
            ->emptyStateIcon('heroicon-o-tag');
    }
}
