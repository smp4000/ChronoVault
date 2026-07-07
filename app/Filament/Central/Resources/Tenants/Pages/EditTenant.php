<?php

/**
 * =========================================================================
 * EditTenant — Bearbeiten-Seite mit zweistufigem Löschkonzept
 * =========================================================================
 *
 * Zweck:
 *   Stammdaten-Bearbeitung plus Header-Aktionen für Archivieren /
 *   Wiederherstellen / endgültiges Löschen. Die Löschlogik läuft
 *   ausschließlich über App\Actions\Tenancy\DeleteTenantAction
 *   (siehe Begründung in Tables\TenantsTable).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Actions\Tenancy\DeleteTenantAction;
use App\Filament\Central\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Archivieren')
                ->modalHeading('Mandant archivieren')
                ->modalDescription('Der Mandant wird deaktiviert, alle Daten bleiben erhalten. Dieser Vorgang kann rückgängig gemacht werden.')
                ->successNotificationTitle('Mandant archiviert')
                ->using(fn (Tenant $record) => app(DeleteTenantAction::class)->archive($record)),

            RestoreAction::make()
                ->label('Wiederherstellen')
                ->successNotificationTitle('Mandant wiederhergestellt'),

            Action::make('forceDelete')
                ->label('Endgültig löschen')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->visible(fn (): bool => auth()->user()?->can('forceDelete', $this->getRecord()) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Mandant endgültig löschen')
                ->modalDescription('ACHTUNG: Die komplette Datenbank des Mandanten wird unwiderruflich gelöscht. Dieser Vorgang kann NICHT rückgängig gemacht werden.')
                ->modalSubmitActionLabel('Unwiderruflich löschen')
                ->action(function (): void {
                    /** @var Tenant $tenant */
                    $tenant = $this->getRecord();

                    app(DeleteTenantAction::class)->execute($tenant);

                    Notification::make()
                        ->title('Mandant endgültig gelöscht')
                        ->body('Datensatz und Mandanten-Datenbank wurden entfernt.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Mandant gespeichert';
    }
}
