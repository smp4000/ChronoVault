<?php

/**
 * =========================================================================
 * BrandResource — Marken-Stammdaten im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Verwaltung des Markenkatalogs eines Mandanten (Tenant-Datenbank,
 *   ADR-009). Zugriff regelt App\Policies\BrandPolicy über die
 *   master_data.*-Berechtigungen.
 *
 * Verantwortlichkeiten:
 *   - Navigation/Labels (deutsch), Global Search, Seiten-Routing
 *   - Formular/Tabelle liegen in Schemas/ bzw. Tables/ (keine Logik hier)
 *   - CalibersRelationManager: Kaliber direkt an der Marke pflegen
 *
 * WARUM getEloquentQuery() ohne SoftDeletingScope:
 *   Der TrashedFilter der Tabelle braucht Zugriff auf gelöschte Zeilen —
 *   Standardansicht blendet sie weiterhin aus (Filter-Default).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Brands;

use App\Filament\App\Resources\Brands\Pages\CreateBrand;
use App\Filament\App\Resources\Brands\Pages\EditBrand;
use App\Filament\App\Resources\Brands\Pages\ListBrands;
use App\Filament\App\Resources\Brands\RelationManagers\CalibersRelationManager;
use App\Filament\App\Resources\Brands\Schemas\BrandForm;
use App\Filament\App\Resources\Brands\Tables\BrandsTable;
use App\Models\Brand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Marke';

    protected static ?string $pluralModelLabel = 'Marken';

    protected static string|\UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandsTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getRelations(): array
    {
        return [
            CalibersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
