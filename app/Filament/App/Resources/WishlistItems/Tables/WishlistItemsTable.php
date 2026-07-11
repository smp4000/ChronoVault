<?php

/**
 * =========================================================================
 * WishlistItemsTable — Tabellen-Definition der Wunschliste
 * =========================================================================
 * Marktwert grün, sobald der Zielpreis erreicht ist; „Jetzt bewerten"
 * stößt die KI-Recherche sofort an (ValuateWishlistItemAction —
 * verschickt bei Zielpreis-Erreichen auch die Alarm-Mail).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\WishlistItems\Tables;

use App\Actions\Wishlist\ValuateWishlistItemAction;
use App\Enums\WishlistStatus;
use App\Models\WishlistItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class WishlistItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('brand'))
            ->columns([
                TextColumn::make('model_name')
                    ->label('Wunschmodell')
                    ->state(fn (WishlistItem $record): string => $record->brand->name.' '.$record->model_name)
                    ->description(fn (WishlistItem $record): ?string => $record->reference_number
                        ? 'Ref. '.$record->reference_number
                        : null)
                    ->weight('semibold')
                    ->searchable(['model_name', 'reference_number']),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('target_price')
                    ->label('Zielpreis')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('current_market_value')
                    ->label('Marktwert')
                    ->money('EUR')
                    ->placeholder('noch nicht bewertet')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (WishlistItem $record): string => $record->isTargetReached() ? 'success' : 'gray')
                    ->icon(fn (WishlistItem $record): ?string => $record->isTargetReached() ? 'heroicon-m-check-circle' : null)
                    ->description(function (WishlistItem $record): ?string {
                        if ($record->value_low === null && $record->value_high === null) {
                            return null;
                        }

                        $eur = fn ($value): string => $value !== null
                            ? number_format((float) $value, 0, ',', '.').' €'
                            : '—';

                        return 'Spanne '.$eur($record->value_low).' – '.$eur($record->value_high);
                    }),

                TextColumn::make('last_valuation_at')
                    ->label('Zuletzt bewertet')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(WishlistStatus::class),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                self::valuateAction(),

                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Wunschmodell löschen')
                    ->successNotificationTitle('Wunschmodell gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Wunschmodell wiederhergestellt'),
            ])
            ->emptyStateHeading('Noch keine Wunschmodelle')
            ->emptyStateDescription('Legen Sie Uhren an, die Sie suchen — die nächtliche KI-Recherche beobachtet die Marktpreise und meldet sich, wenn Ihr Zielpreis erreicht ist.')
            ->emptyStateIcon('heroicon-o-heart');
    }

    /**
     * Sofort-Bewertung per KI — inkl. Zielpreis-Alarm (Action).
     */
    private static function valuateAction(): Action
    {
        return Action::make('valuate')
            ->label('Jetzt bewerten')
            ->icon('heroicon-m-sparkles')
            ->color('info')
            ->visible(fn (WishlistItem $record): bool => ! $record->trashed()
                && (auth()->user()?->can('watches.update') ?? false))
            ->action(function (WishlistItem $record): void {
                try {
                    $record = app(ValuateWishlistItemAction::class)->execute($record);
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title('Bewertung fehlgeschlagen')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Marktwert: '.number_format((float) $record->current_market_value, 0, ',', '.').' €')
                    ->body($record->isTargetReached()
                        ? 'Zielpreis erreicht — die Alarm-Mail ist unterwegs!'
                        : 'Zielpreis noch nicht erreicht — die Beobachtung läuft weiter.')
                    ->send();
            });
    }
}
