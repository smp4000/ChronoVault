<?php

/**
 * =========================================================================
 * AuctionResource — Auktionen im Tenant-Panel (Modul 8)
 * =========================================================================
 *
 * Zweck:
 *   Verwaltung der Auktions-Ereignisse mit Losverwaltung
 *   (LotsRelationManager). Zugriff regelt App\Policies\AuctionPolicy
 *   (auctions.*).
 *
 * WICHTIG:
 *   Einliefern und Abrechnen der Lose läuft über die Domain-Actions
 *   (AddLotToAuctionAction / SettleLotAction) — sie halten den
 *   Uhren-Status synchron und erzeugen beim Zuschlag den Verkaufsbeleg.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Auctions;

use App\Filament\App\Resources\Auctions\Pages\CreateAuction;
use App\Filament\App\Resources\Auctions\Pages\EditAuction;
use App\Filament\App\Resources\Auctions\Pages\ListAuctions;
use App\Filament\App\Resources\Auctions\RelationManagers\LotsRelationManager;
use App\Filament\App\Resources\Auctions\Schemas\AuctionForm;
use App\Filament\App\Resources\Auctions\Tables\AuctionsTable;
use App\Models\Auction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AuctionResource extends Resource
{
    protected static ?string $model = Auction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $modelLabel = 'Auktion';

    protected static ?string $pluralModelLabel = 'Auktionen';

    protected static string|\UnitEnum|null $navigationGroup = 'Verkauf';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AuctionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuctionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuctions::route('/'),
            'create' => CreateAuction::route('/create'),
            'edit' => EditAuction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
