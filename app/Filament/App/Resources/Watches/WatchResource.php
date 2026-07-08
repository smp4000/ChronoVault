<?php

/**
 * =========================================================================
 * WatchResource — Uhrenbestand im Tenant-Panel (Kernmodul)
 * =========================================================================
 *
 * Zweck:
 *   Verwaltung des Uhrenbestands eines Betriebs — die zentrale Resource
 *   der Anwendung. Zugriff regelt App\Policies\WatchPolicy über die
 *   watches.*-Berechtigungen.
 *
 * Verantwortlichkeiten:
 *   - Navigation/Labels (deutsch), Global Search, Seiten-Routing
 *   - Formular/Tabelle liegen in Schemas/ bzw. Tables/ (keine Logik hier)
 *
 * Navigation:
 *   Eigene Gruppe „Bestand" — sortiert vor „Stammdaten" und „Verwaltung"
 *   (alphabetisch), denn hier arbeiten die Nutzer täglich.
 *
 * WARUM getEloquentQuery() ohne SoftDeletingScope:
 *   Der TrashedFilter der Tabelle braucht Zugriff auf gelöschte Zeilen —
 *   Standardansicht blendet sie weiterhin aus (Filter-Default).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches;

use App\Filament\App\Resources\Watches\Pages\CreateWatch;
use App\Filament\App\Resources\Watches\Pages\EditWatch;
use App\Filament\App\Resources\Watches\Pages\ListWatches;
use App\Filament\App\Resources\Watches\Schemas\WatchForm;
use App\Filament\App\Resources\Watches\Tables\WatchesTable;
use App\Models\Watch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WatchResource extends Resource
{
    protected static ?string $model = Watch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'Uhr';

    protected static ?string $pluralModelLabel = 'Uhren';

    protected static string|\UnitEnum|null $navigationGroup = 'Bestand';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'model_name';

    public static function form(Schema $schema): Schema
    {
        return WatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WatchesTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['model_name', 'reference_number', 'serial_number', 'stock_number', 'brand.name'];
    }

    /**
     * Global-Search-Treffer als "Marke Modell (Referenz)" anzeigen —
     * der Modellname allein ist bei Uhren nicht eindeutig genug.
     */
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Watch $record */
        return $record->fullName();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\ServiceRecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWatches::route('/'),
            'create' => CreateWatch::route('/create'),
            'edit' => EditWatch::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
