<?php

/**
 * =========================================================================
 * TenantsTable — Tabellen-Definition der Mandantenübersicht
 * =========================================================================
 *
 * Zweck:
 *   Listet alle Mandanten mit Status, Domain und Erstelldatum. Enthält
 *   das zweistufige Löschkonzept:
 *     - „Archivieren" (Soft Delete, reversibel) via DeleteTenantAction::archive()
 *     - „Endgültig löschen" (inkl. Tenant-DB!) via DeleteTenantAction::execute()
 *
 * WARUM keine Standard-ForceDelete-Action:
 *   Filaments ForceDeleteAction ruft nur $record->forceDelete() auf —
 *   die Tenant-DATENBANK bliebe als Waise zurück (die automatische
 *   Lösch-Pipeline wurde aus Sicherheitsgründen entfernt, siehe
 *   TenancyServiceProvider). Deshalb eine eigene Action, die durch die
 *   DeleteTenantAction läuft.
 *
 * WARUM keine Bulk-Löschaktionen:
 *   Mandanten-Löschung ist eine bewusste Einzelfall-Entscheidung —
 *   Massenoperationen wären hier ein Sicherheitsrisiko.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Tables;

use App\Actions\Tenancy\DeleteTenantAction;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager Loading der Domains — verhindert N+1-Queries in der Domain-Spalte.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('domains'))
            ->columns([
                TextColumn::make('name')
                    ->label('Firmenname')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Tenant $record): string => $record->slug),

                TextColumn::make('domains.domain')
                    ->label('Domain')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-m-globe-alt'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TenantStatus::class),

                TrashedFilter::make()
                    ->label('Archivierte'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Öffnen')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    // Schema + Port kommen aus APP_URL — lokal
                    // http://…:8000, in Produktion https://… ohne Port.
                    ->url(function (Tenant $record): ?string {
                        $domain = $record->primaryDomain();

                        if ($domain === null) {
                            return null;
                        }

                        $appUrl = parse_url((string) config('app.url'));
                        $scheme = $appUrl['scheme'] ?? 'https';
                        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

                        return $scheme.'://'.$domain.$port.'/app';
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (Tenant $record): bool => ! $record->trashed()),

                EditAction::make(),

                DeleteAction::make()
                    ->label('Archivieren')
                    ->modalHeading('Mandant archivieren')
                    ->modalDescription('Der Mandant wird deaktiviert, alle Daten bleiben erhalten. Dieser Vorgang kann rückgängig gemacht werden.')
                    ->successNotificationTitle('Mandant archiviert')
                    // Über die Action statt direktem delete(): setzt zusätzlich den Status.
                    ->using(fn (Tenant $record) => app(DeleteTenantAction::class)->archive($record)),

                RestoreAction::make()
                    ->label('Wiederherstellen')
                    ->successNotificationTitle('Mandant wiederhergestellt'),

                Action::make('forceDelete')
                    ->label('Endgültig löschen')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    // Sichtbarkeit = Policy-Check: nur archivierte Tenants, nur zentral.
                    ->visible(fn (Tenant $record): bool => auth()->user()?->can('forceDelete', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Mandant endgültig löschen')
                    ->modalDescription('ACHTUNG: Die komplette Datenbank des Mandanten wird unwiderruflich gelöscht. Dieser Vorgang kann NICHT rückgängig gemacht werden.')
                    ->modalSubmitActionLabel('Unwiderruflich löschen')
                    ->action(function (Tenant $record): void {
                        app(DeleteTenantAction::class)->execute($record);

                        Notification::make()
                            ->title('Mandant endgültig gelöscht')
                            ->body('Datensatz und Mandanten-Datenbank wurden entfernt.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Noch keine Mandanten')
            ->emptyStateDescription('Legen Sie den ersten Mandanten an — Datenbank, Rollen und Inhaber-Zugang werden automatisch eingerichtet.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
