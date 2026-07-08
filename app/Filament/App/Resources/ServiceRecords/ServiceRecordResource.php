<?php

/**
 * =========================================================================
 * ServiceRecordResource — Servicevorgänge im Tenant-Panel (Modul 6)
 * =========================================================================
 *
 * Zweck:
 *   Übersicht und Erfassung aller Wartungs-/Reparaturvorgänge.
 *   Zugriff regelt App\Policies\ServiceRecordPolicy (services.*).
 *
 * WICHTIG:
 *   Start/Abschluss laufen über StartServiceAction/CompleteServiceAction
 *   (Uhren-Status-Sync mit Restore). Formular/Tabelle werden vom
 *   ServiceRecordsRelationManager wiederverwendet (withWatch: false).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceRecords;

use App\Filament\App\Resources\ServiceRecords\Pages\CreateServiceRecord;
use App\Filament\App\Resources\ServiceRecords\Pages\EditServiceRecord;
use App\Filament\App\Resources\ServiceRecords\Pages\ListServiceRecords;
use App\Filament\App\Resources\ServiceRecords\Schemas\ServiceRecordForm;
use App\Filament\App\Resources\ServiceRecords\Tables\ServiceRecordsTable;
use App\Models\ServiceRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceRecordResource extends Resource
{
    protected static ?string $model = ServiceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'Servicevorgang';

    protected static ?string $pluralModelLabel = 'Service & Wartung';

    protected static string|\UnitEnum|null $navigationGroup = 'Bestand';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function form(Schema $schema): Schema
    {
        return ServiceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRecords::route('/'),
            'create' => CreateServiceRecord::route('/create'),
            'edit' => EditServiceRecord::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
