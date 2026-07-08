<?php

/**
 * =========================================================================
 * ServiceRecordsTable — Tabellen-Definition der Servicevorgänge
 * =========================================================================
 *
 * Zweck:
 *   Wird von der ServiceRecordResource UND dem RelationManager genutzt
 *   (withWatch: false blendet die Uhren-Spalte aus). Die
 *   "Abschließen"-Aktion läuft über die CompleteServiceAction
 *   (Status-Restore an der Uhr).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceRecords\Tables;

use App\Actions\Services\CompleteServiceAction;
use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Models\ServiceRecord;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRecordsTable
{
    public static function configure(Table $table, bool $withWatch = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $withWatch
                ? $query->with(['watch.brand', 'contact'])
                : $query->with('contact'))
            ->columns(array_filter([
                TextColumn::make('submitted_at')
                    ->label('Eingereicht')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->label('Art')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                $withWatch
                    ? TextColumn::make('watch.model_name')
                        ->label('Uhr')
                        ->state(fn (ServiceRecord $record): string => $record->watch->fullName())
                        ->weight('semibold')
                    : null,

                TextColumn::make('contact.last_name')
                    ->label('Werkstatt')
                    ->state(fn (ServiceRecord $record): string => $record->contact?->displayName() ?? 'Hausintern'),

                TextColumn::make('cost')
                    ->label('Kosten')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('completed_at')
                    ->label('Abgeschlossen')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('warranty_until')
                    ->label('Garantie bis')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]))
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ServiceStatus::class),

                SelectFilter::make('type')
                    ->label('Art')
                    ->options(ServiceType::class),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                self::completeAction(),

                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Servicevorgang löschen')
                    ->successNotificationTitle('Servicevorgang gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Servicevorgang wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Servicevorgang endgültig löschen')
                    ->successNotificationTitle('Servicevorgang endgültig gelöscht'),
            ])
            ->emptyStateHeading('Keine Servicevorgänge')
            ->emptyStateDescription('Revisionen und Reparaturen erscheinen hier als Service-Historie.')
            ->emptyStateIcon('heroicon-o-wrench-screwdriver');
    }

    /**
     * "Abschließen"-Aktion: setzt completed_at/Kosten/Garantie über die
     * CompleteServiceAction und stellt den Uhren-Status wieder her.
     */
    private static function completeAction(): Action
    {
        return Action::make('completeService')
            ->label('Abschließen')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn (ServiceRecord $record): bool => ! $record->isCompleted()
                && ! $record->trashed()
                && (auth()->user()?->can('services.update') ?? false))
            ->modalHeading('Servicevorgang abschließen')
            ->modalSubmitActionLabel('Abschließen')
            ->form([
                DatePicker::make('completed_at')
                    ->label('Abgeschlossen am')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),

                TextInput::make('cost')
                    ->label('Endgültige Kosten')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->helperText('Leer lassen, um die erfassten Kosten beizubehalten.'),

                DatePicker::make('warranty_until')
                    ->label('Service-Garantie bis'),

                Textarea::make('notes')
                    ->label('Abschluss-Notiz')
                    ->rows(2),
            ])
            ->action(function (ServiceRecord $record, array $data): void {
                app(CompleteServiceAction::class)->execute($record, $data);

                Notification::make()
                    ->success()
                    ->title('Service abgeschlossen')
                    ->body('Die Uhr ist zurück im vorherigen Bestandsstatus.')
                    ->send();
            });
    }
}
