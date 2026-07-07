<?php

/**
 * =========================================================================
 * ListTenants — Übersichtsseite der Mandanten
 * =========================================================================
 *
 * Zweck:
 *   Listet alle Mandanten (Definition in Tables\TenantsTable) und bietet
 *   die Anlage neuer Mandanten über den Header-Button.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Filament\Central\Resources\Tenants\TenantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Mandant anlegen')
                ->icon('heroicon-m-plus'),
        ];
    }
}
