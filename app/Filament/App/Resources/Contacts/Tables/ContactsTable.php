<?php

/**
 * =========================================================================
 * ContactsTable — Tabellen-Definition des Kundenstamms (Tenant-Panel)
 * =========================================================================
 *
 * Lösch-Autorisierung inkl. Referenz-Schutz (Kontakt mit Belegen)
 * übernimmt die ContactPolicy — hier steht KEINE eigene Logik.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Contacts\Tables;

use App\Enums\ContactType;
use App\Models\Contact;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->state(fn (Contact $record): string => $record->displayName())
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where(fn ($q) => $q
                            ->where('company_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")))
                    ->weight('semibold'),

                TextColumn::make('type')
                    ->label('Art')
                    ->badge(),

                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('city')
                    ->label('Ort')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('transactions_count')
                    ->label('Belege')
                    ->counts('transactions')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Angelegt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Art')
                    ->options(ContactType::class),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Kontakt löschen')
                    ->successNotificationTitle('Kontakt gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Kontakt wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Kontakt endgültig löschen')
                    ->successNotificationTitle('Kontakt endgültig gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Kontakte')
            ->emptyStateDescription('Legen Sie Käufer, Lieferanten und Einlieferer an.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
