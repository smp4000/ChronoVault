<?php

/**
 * =========================================================================
 * PriceProposalResource — Preisvorschläge aus dem Shop (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Übersicht aller Preisvorschläge von der Shop-Detailseite mit
 *   Annehmen/Ablehnen und Antworten-per-Mail. Kein Anlegen/Bearbeiten —
 *   Vorschläge entstehen ausschließlich über den öffentlichen Shop
 *   (ShopController::propose speichert + mailt).
 *
 * Zugriff: App\Policies\PriceProposalPolicy (watches.*-Berechtigungen).
 * Navigations-Badge: Anzahl unbearbeiteter Vorschläge.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\PriceProposals;

use App\Enums\PriceProposalStatus;
use App\Filament\App\Resources\PriceProposals\Pages\ListPriceProposals;
use App\Filament\App\Resources\PriceProposals\Tables\PriceProposalsTable;
use App\Models\PriceProposal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PriceProposalResource extends Resource
{
    protected static ?string $model = PriceProposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static ?string $modelLabel = 'Preisvorschlag';

    protected static ?string $pluralModelLabel = 'Preisvorschläge';

    protected static string|\UnitEnum|null $navigationGroup = 'Verkauf';

    protected static ?int $navigationSort = 25;

    public static function table(Table $table): Table
    {
        return PriceProposalsTable::configure($table);
    }

    /**
     * Badge in der Navigation: Anzahl der unbearbeiteten Vorschläge.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = PriceProposal::query()
            ->where('status', PriceProposalStatus::New->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceProposals::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
