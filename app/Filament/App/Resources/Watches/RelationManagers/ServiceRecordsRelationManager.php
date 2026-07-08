<?php

/**
 * =========================================================================
 * ServiceRecordsRelationManager — Service-Historie direkt an der Uhr
 * =========================================================================
 *
 * Zweck:
 *   Zeigt auf der Uhren-Bearbeitungsseite alle Servicevorgänge und
 *   erlaubt Start/Abschluss im Kontext der Uhr. Formular und Tabelle
 *   werden aus der ServiceRecordResource wiederverwendet
 *   (withWatch: false); die Anlage läuft über die StartServiceAction.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches\RelationManagers;

use App\Actions\Services\StartServiceAction;
use App\Filament\App\Resources\ServiceRecords\Schemas\ServiceRecordForm;
use App\Filament\App\Resources\ServiceRecords\Tables\ServiceRecordsTable;
use App\Models\ServiceRecord;
use App\Models\Watch;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceRecords';

    protected static ?string $title = 'Service & Wartung';

    public function form(Schema $schema): Schema
    {
        return ServiceRecordForm::configure($schema, withWatch: false);
    }

    public function table(Table $table): Table
    {
        return ServiceRecordsTable::configure($table, withWatch: false)
            ->modelLabel('Servicevorgang')
            ->pluralModelLabel('Servicevorgänge')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->with('contact'))
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ServiceRecord {
                        /** @var Watch $watch */
                        $watch = $this->getOwnerRecord();

                        return app(StartServiceAction::class)->execute($watch, $data);
                    }),
            ]);
    }
}
