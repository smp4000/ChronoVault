<?php

/**
 * =========================================================================
 * WishlistWidget — Wunschliste auf dem Dashboard
 * =========================================================================
 *
 * Zweck:
 *   Zeigt alle beobachteten Wunschmodelle (Uhren mit Status
 *   "Wunschliste") direkt auf dem Dashboard: Zielpreis, aktueller
 *   Marktwert (grün + Haken bei Ziel-Erreichen), letzte Bewertung.
 *   Zeilenklick öffnet die Uhr zum Bearbeiten.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Enums\WatchStatus;
use App\Filament\App\Resources\Watches\WatchResource;
use App\Models\Watch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class WishlistWidget extends TableWidget
{
    protected static ?int $sort = 25;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Nur zeigen, wenn es überhaupt Wunschmodelle gibt
        return (auth()->user()?->can('watches.view') ?? false)
            && Watch::query()->where('status', WatchStatus::Wishlist->value)->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Wunschliste — beobachtete Modelle')
            ->description('Die nächtliche KI-Recherche aktualisiert die Marktwerte; bei Erreichen des Zielpreises kommt eine Alarm-Mail.')
            ->query(
                Watch::query()
                    ->where('status', WatchStatus::Wishlist->value)
                    ->with('brand')
                    ->orderBy('created_at', 'desc'),
            )
            ->columns([
                TextColumn::make('model_name')
                    ->label('Wunschmodell')
                    ->state(fn (Watch $record): string => $record->fullName())
                    ->weight('semibold'),

                TextColumn::make('wishlist_target_price')
                    ->label('Zielpreis')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd(),

                TextColumn::make('current_market_value')
                    ->label('Marktwert')
                    ->money('EUR')
                    ->placeholder('noch nicht bewertet')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Watch $record): string => $record->wishlistTargetReached() ? 'success' : 'gray')
                    ->icon(fn (Watch $record): ?string => $record->wishlistTargetReached() ? 'heroicon-m-check-circle' : null),

                TextColumn::make('last_valuation_at')
                    ->label('Zuletzt bewertet')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (Watch $record): string => WatchResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
