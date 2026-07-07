<?php

/**
 * =========================================================================
 * CalibersRelationManager — Kaliber direkt an der Marke pflegen
 * =========================================================================
 *
 * Zweck:
 *   Zeigt auf der Marken-Bearbeitungsseite die zugehörigen Kaliber und
 *   erlaubt Anlage/Bearbeitung im Kontext des Herstellers. Formular und
 *   Tabelle werden aus der CaliberResource WIEDERVERWENDET
 *   (`withBrand: false` blendet Hersteller-Feld/-Spalte aus — der
 *   Hersteller ist hier der Owner-Record).
 *
 * WARUM modifyQueryUsing ohne SoftDeletingScope:
 *   Anders als die Resource (getEloquentQuery) baut der Relation Manager
 *   seine Query aus der Beziehung — der Papierkorb-Filter braucht die
 *   Scope-Entfernung deshalb hier an der Tabelle.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Brands\RelationManagers;

use App\Filament\App\Resources\Calibers\Schemas\CaliberForm;
use App\Filament\App\Resources\Calibers\Tables\CalibersTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CalibersRelationManager extends RelationManager
{
    protected static string $relationship = 'calibers';

    protected static ?string $title = 'Kaliber';

    public function form(Schema $schema): Schema
    {
        return CaliberForm::configure($schema, withBrand: false);
    }

    public function table(Table $table): Table
    {
        return CalibersTable::configure($table, withBrand: false)
            ->modelLabel('Kaliber')
            ->pluralModelLabel('Kaliber')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
