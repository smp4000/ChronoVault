<?php

/**
 * =========================================================================
 * WishlistItemResource — Wunschliste im Tenant-Panel (Sammler-Werkzeug)
 * =========================================================================
 *
 * Zweck:
 *   Wunschmodelle beobachten: Marke/Modell/Referenz + Zielpreis. Die
 *   nächtliche Wertermittlung (wishlist:update-values) pflegt den
 *   Marktwert, „Jetzt bewerten" macht es sofort. Zielpreis erreicht →
 *   grünes Badge + Alarm-Mail (ValuateWishlistItemAction).
 *
 * Zugriff: App\Policies\WishlistItemPolicy (watches.*-Berechtigungen).
 * Navigations-Badge: Anzahl aktiver Einträge mit erreichtem Zielpreis.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\WishlistItems;

use App\Enums\WishlistStatus;
use App\Filament\App\Resources\WishlistItems\Pages\CreateWishlistItem;
use App\Filament\App\Resources\WishlistItems\Pages\EditWishlistItem;
use App\Filament\App\Resources\WishlistItems\Pages\ListWishlistItems;
use App\Filament\App\Resources\WishlistItems\Schemas\WishlistItemForm;
use App\Filament\App\Resources\WishlistItems\Tables\WishlistItemsTable;
use App\Models\WishlistItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WishlistItemResource extends Resource
{
    protected static ?string $model = WishlistItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $modelLabel = 'Wunschmodell';

    protected static ?string $pluralModelLabel = 'Wunschliste';

    protected static string|\UnitEnum|null $navigationGroup = 'Bestand';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return WishlistItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WishlistItemsTable::configure($table);
    }

    /**
     * Badge: aktive Wunschmodelle, deren Zielpreis erreicht ist.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = WishlistItem::query()
            ->where('status', WishlistStatus::Active->value)
            ->whereNotNull('target_price')
            ->whereNotNull('current_market_value')
            ->whereColumn('current_market_value', '<=', 'target_price')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWishlistItems::route('/'),
            'create' => CreateWishlistItem::route('/create'),
            'edit' => EditWishlistItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
