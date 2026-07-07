<?php

/**
 * =========================================================================
 * UsersTable — Tabellen-Definition der Benutzerverwaltung (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Mitarbeiterliste mit Rollen-Badges (deutsche Labels aus UserRole).
 *   Lösch-Autorisierung übernimmt die UserPolicy (Selbstlöschungs- und
 *   Owner-Hierarchie-Schutz) — hier steht bewusst KEINE eigene Logik.
 *
 * WARUM keine Bulk-Löschaktion:
 *   Filament prüft bei Bulk-Aktionen die Policy nicht pro Datensatz —
 *   der Selbstlöschungs-/Hierarchie-Schutz der UserPolicy würde
 *   umgangen. Benutzer werden bewusst einzeln gelöscht.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager Loading der Rollen — verhindert N+1 in der Rollen-Spalte.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('email')
                    ->label('E-Mail-Adresse')
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->copyMessage('E-Mail-Adresse kopiert'),

                TextColumn::make('roles.name')
                    ->label('Rollen')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state
                    )
                    ->color(
                        fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray'
                    ),

                TextColumn::make('created_at')
                    ->label('Angelegt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rolle')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record): string => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name
                    ),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Benutzer löschen')
                    ->successNotificationTitle('Benutzer gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Benutzer')
            ->emptyStateDescription('Legen Sie Mitarbeiter an und weisen Sie ihnen Rollen zu.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
