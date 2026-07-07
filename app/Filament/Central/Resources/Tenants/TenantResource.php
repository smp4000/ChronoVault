<?php

/**
 * =========================================================================
 * TenantResource — Mandantenverwaltung im zentralen Admin-Panel
 * =========================================================================
 *
 * Zweck:
 *   CRUD-Oberfläche für Mandanten (Händler, Juweliere, Auktionshäuser).
 *   Reine UI-Schicht: Formular- und Tabellen-Definitionen liegen in
 *   Schemas/TenantForm bzw. Tables/TenantsTable; die Business-Logik
 *   (Provisioning, Löschung) in App\Actions\Tenancy\*.
 *
 * Verantwortlichkeiten:
 *   - Navigation/Labels (deutsch) und Global Search
 *   - Seiten-Routing (List/Create/Edit)
 *   - Soft-Delete-Handling im Routing (withoutGlobalScopes)
 *
 * Abhängigkeiten:
 *   - App\Models\Tenant, App\Policies\TenantPolicy (Auto-Discovery)
 *   - App\Actions\Tenancy\CreateTenantAction (via CreateTenant-Page)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants;

use App\Filament\Central\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Central\Resources\Tenants\Pages\EditTenant;
use App\Filament\Central\Resources\Tenants\Pages\ListTenants;
use App\Filament\Central\Resources\Tenants\Schemas\TenantForm;
use App\Filament\Central\Resources\Tenants\Tables\TenantsTable;
use App\Models\Tenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    /** Deutsche UI-Labels (Code Englisch, UI Deutsch — Projektregel). */
    protected static ?string $modelLabel = 'Mandant';

    protected static ?string $pluralModelLabel = 'Mandanten';

    protected static string|\UnitEnum|null $navigationGroup = 'Plattform';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return TenantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantsTable::configure($table);
    }

    /** Global Search: Mandanten über Name und Slug auffindbar. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }

    /**
     * Auch archivierte (soft-deleted) Mandanten müssen über die URL
     * erreichbar bleiben — sonst wäre Wiederherstellen unmöglich.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
