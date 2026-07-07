<?php

/**
 * =========================================================================
 * CaliberResource — Kaliber-Stammdaten im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Verwaltung der Uhrwerke/Kaliber eines Mandanten (Tenant-Datenbank,
 *   ADR-009). Zugriff regelt App\Policies\CaliberPolicy über die
 *   master_data.*-Berechtigungen.
 *
 * Verantwortlichkeiten:
 *   - Navigation/Labels (deutsch), Global Search, Seiten-Routing
 *   - Formular/Tabelle liegen in Schemas/ bzw. Tables/ und werden vom
 *     CalibersRelationManager (BrandResource) WIEDERVERWENDET —
 *     eine Definition, zwei Einstiegspunkte.
 *
 * WARUM getEloquentQuery() ohne SoftDeletingScope:
 *   Der TrashedFilter der Tabelle braucht Zugriff auf gelöschte Zeilen —
 *   Standardansicht blendet sie weiterhin aus (Filter-Default).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Calibers;

use App\Filament\App\Resources\Calibers\Pages\CreateCaliber;
use App\Filament\App\Resources\Calibers\Pages\EditCaliber;
use App\Filament\App\Resources\Calibers\Pages\ListCalibers;
use App\Filament\App\Resources\Calibers\Schemas\CaliberForm;
use App\Filament\App\Resources\Calibers\Tables\CalibersTable;
use App\Models\Caliber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CaliberResource extends Resource
{
    protected static ?string $model = Caliber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $modelLabel = 'Kaliber';

    protected static ?string $pluralModelLabel = 'Kaliber';

    protected static string|\UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CaliberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalibersTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'brand.name'];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalibers::route('/'),
            'create' => CreateCaliber::route('/create'),
            'edit' => EditCaliber::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
