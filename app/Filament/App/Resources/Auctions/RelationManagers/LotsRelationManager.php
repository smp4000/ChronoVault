<?php

/**
 * =========================================================================
 * LotsRelationManager — Losverwaltung an der Auktion (Modul 8)
 * =========================================================================
 *
 * Zweck:
 *   Einliefern von Uhren als Lose und Abrechnung (Zuschlag, Rückgang,
 *   Rückzug) direkt an der Auktion — alles über die Domain-Actions:
 *   - AddLotToAuctionAction: merkt den Uhren-Status, Uhr → "In Auktion"
 *   - SettleLotAction::sold(): Verkaufsbeleg (Modul 5) + Los zugeschlagen
 *   - SettleLotAction::unsold()/withdraw(): Uhren-Status-RESTORE
 *
 * Guards der Actions (RuntimeException) werden als deutsche
 * Danger-Notification angezeigt und brechen die Aktion sauber ab (Halt).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Auctions\RelationManagers;

use App\Actions\Auctions\AddLotToAuctionAction;
use App\Actions\Auctions\SettleLotAction;
use App\Enums\AuctionLotStatus;
use App\Enums\PaymentMethod;
use App\Enums\WatchStatus;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Contact;
use App\Models\Watch;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class LotsRelationManager extends RelationManager
{
    protected static string $relationship = 'lots';

    protected static ?string $title = 'Lose';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('watch_id')
                    ->label('Uhr')
                    ->options(fn (): array => Watch::query()
                        ->with('brand')
                        ->whereNot('status', WatchStatus::Sold->value)
                        ->get()
                        ->mapWithKeys(fn (Watch $watch): array => [$watch->id => $watch->fullName()])
                        ->all())
                    ->searchable()
                    ->required()
                    ->visibleOn('create')
                    ->helperText('Verkaufte Uhren können nicht eingeliefert werden.')
                    ->columnSpanFull(),

                TextInput::make('lot_number')
                    ->label('Losnummer')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leer lassen für fortlaufende Vergabe.'),

                TextInput::make('starting_price')
                    ->label('Startpreis')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€'),

                TextInput::make('estimate_low')
                    ->label('Schätzpreis von')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€'),

                TextInput::make('estimate_high')
                    ->label('Schätzpreis bis')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€'),

                TextInput::make('reserve_price')
                    ->label('Limit (Reserve)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->helperText('Mindestpreis des Einlieferers — intern.'),

                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(2),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel('Los')
            ->pluralModelLabel('Lose')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['watch.brand', 'buyer'])
                ->withCount('bids')
                ->withMax('bids', 'amount'))
            ->columns([
                TextColumn::make('lot_code')
                    ->label('Los')
                    ->weight('semibold')
                    ->searchable()
                    ->description(fn (AuctionLot $lot): string => 'Nr. '.$lot->lot_number),

                TextColumn::make('watch.model_name')
                    ->label('Uhr')
                    ->state(fn (AuctionLot $lot): string => $lot->watch->fullName())
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('estimate_low')
                    ->label('Schätzpreis')
                    ->state(function (AuctionLot $lot): string {
                        $format = fn ($value): string => number_format((float) $value, 0, ',', '.').' €';

                        return match (true) {
                            $lot->estimate_low !== null && $lot->estimate_high !== null => $format($lot->estimate_low).' – '.$format($lot->estimate_high),
                            $lot->estimate_low !== null => 'ab '.$format($lot->estimate_low),
                            $lot->estimate_high !== null => 'bis '.$format($lot->estimate_high),
                            default => '—',
                        };
                    })
                    ->alignEnd(),

                TextColumn::make('reserve_price')
                    ->label('Limit')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bids_max_amount')
                    ->label('Höchstgebot')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd()
                    ->description(fn (AuctionLot $lot): ?string => $lot->bids_count > 0
                        ? $lot->bids_count.' '.($lot->bids_count === 1 ? 'Gebot' : 'Gebote')
                        : null),

                TextColumn::make('hammer_price')
                    ->label('Zuschlag')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd()
                    ->weight('semibold'),

                TextColumn::make('buyer.last_name')
                    ->label('Käufer')
                    ->state(fn (AuctionLot $lot): string => $lot->buyer?->displayName() ?? '—')
                    ->toggleable(),
            ])
            ->defaultSort('lot_number')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AuctionLotStatus::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Uhr einliefern')
                    ->modalHeading('Uhr als Los einliefern')
                    ->createAnother(false)
                    ->using(function (array $data): AuctionLot {
                        /** @var Auction $auction */
                        $auction = $this->getOwnerRecord();
                        $watch = Watch::findOrFail($data['watch_id']);

                        try {
                            return app(AddLotToAuctionAction::class)->execute($auction, $watch, $data);
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Einliefern nicht möglich')
                                ->body($exception->getMessage())
                                ->send();

                            throw new Halt;
                        }
                    })
                    ->successNotificationTitle('Los eingeliefert — die Uhr ist jetzt „In Auktion".'),
            ])
            ->recordActions([
                self::soldAction(),
                self::unsoldAction(),
                self::withdrawAction(),
                self::bidsAction(),

                EditAction::make()
                    ->visible(fn (AuctionLot $lot): bool => $lot->isOpen()),

                DeleteAction::make()
                    ->modalHeading('Los löschen')
                    ->successNotificationTitle('Los gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Lose')
            ->emptyStateDescription('Liefern Sie Uhren aus dem Bestand als Lose ein.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    /**
     * Zuschlag: Hammerpreis + Käufer → Verkaufsbeleg über die Action.
     */
    private static function soldAction(): Action
    {
        return Action::make('settleSold')
            ->label('Zuschlag')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn (AuctionLot $lot): bool => $lot->isOpen()
                && ! $lot->trashed()
                && (auth()->user()?->can('auctions.update') ?? false))
            ->modalHeading(fn (AuctionLot $lot): string => 'Zuschlag für Los '.$lot->lot_code)
            ->modalSubmitActionLabel('Zuschlag erfassen')
            // Höchstes Online-Gebot als Vorschlag (Preis + Bieter) —
            // Saal-/Telefongebote können beides einfach überschreiben.
            ->fillForm(function (AuctionLot $lot): array {
                $topBid = $lot->bids()->first();

                return [
                    'hammer_price' => $lot->highestBidAmount(),
                    'buyer_key' => $topBid !== null ? 'bid:'.$topBid->getKey() : null,
                    'settled_at' => now(),
                ];
            })
            ->form([
                TextInput::make('hammer_price')
                    ->label('Hammerpreis')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->required()
                    ->helperText(fn (AuctionLot $lot): ?string => $lot->highestBidAmount() !== null
                        ? 'Vorbefüllt mit dem höchsten Online-Gebot.'
                        : null),

                // Bieter des Loses UND Kundenstamm in einem Feld — der
                // Höchstbietende ist vorausgewählt. Bieter werden beim
                // Zuschlag automatisch als Kontakt angelegt (Action).
                Select::make('buyer_key')
                    ->label('Käufer')
                    ->options(function (AuctionLot $lot): array {
                        $options = [];

                        $bidders = $lot->bids()->get()
                            ->mapWithKeys(fn ($bid): array => [
                                'bid:'.$bid->getKey() => $bid->bidder_name
                                    .' — '.number_format((float) $bid->amount, 0, ',', '.').' €'
                                    .' ('.$bid->bidder_email.')',
                            ])
                            ->all();

                        if ($bidders !== []) {
                            $options['Bieter dieses Loses'] = $bidders;
                        }

                        $options['Kundenstamm'] = Contact::query()
                            ->orderBy('company_name')
                            ->orderBy('last_name')
                            ->get()
                            ->mapWithKeys(fn (Contact $contact): array => ['contact:'.$contact->id => $contact->displayName()])
                            ->all();

                        return $options;
                    })
                    ->searchable()
                    ->live()
                    // Bieter gewählt → Hammerpreis auf dessen Gebot setzen
                    ->afterStateUpdated(function (?string $state, Set $set, AuctionLot $lot): void {
                        if ($state !== null && str_starts_with($state, 'bid:')) {
                            $bid = $lot->bids()->find(substr($state, 4));

                            if ($bid !== null) {
                                $set('hammer_price', (float) $bid->amount);
                            }
                        }
                    })
                    ->helperText('Optional — Bieter werden beim Zuschlag automatisch als Kontakt angelegt.'),

                Select::make('payment_method')
                    ->label('Zahlungsart')
                    ->options(PaymentMethod::class),

                DateTimePicker::make('settled_at')
                    ->label('Zugeschlagen am')
                    ->seconds(false)
                    ->default(now())
                    ->maxDate(now()),
            ])
            ->action(function (AuctionLot $lot, array $data): void {
                // buyer_key ("bid:<id>" | "contact:<id>") in die
                // Action-Parameter übersetzen.
                $buyerKey = $data['buyer_key'] ?? null;
                unset($data['buyer_key']);

                if (is_string($buyerKey) && str_starts_with($buyerKey, 'bid:')) {
                    $data['winning_bid_id'] = substr($buyerKey, 4);
                } elseif (is_string($buyerKey) && str_starts_with($buyerKey, 'contact:')) {
                    $data['buyer_contact_id'] = substr($buyerKey, 8);
                }

                try {
                    app(SettleLotAction::class)->sold($lot, $data);
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Zuschlag nicht möglich')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Zuschlag erfasst')
                    ->body('Verkaufsbeleg wurde angelegt — die Uhr ist jetzt „Verkauft".')
                    ->send();
            });
    }

    /**
     * Gebotsliste (nur intern — enthält Bieterdaten!).
     */
    private static function bidsAction(): Action
    {
        return Action::make('showBids')
            ->label('Gebote')
            ->icon('heroicon-m-list-bullet')
            ->color('gray')
            ->visible(fn (AuctionLot $lot): bool => $lot->bids_count > 0
                && (auth()->user()?->can('auctions.view') ?? false))
            ->modalHeading(fn (AuctionLot $lot): string => 'Gebote für Los '.$lot->lot_code)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Schließen')
            ->modalContent(fn (AuctionLot $lot) => view('filament.auction-lot-bids', [
                'bids' => $lot->bids()->get(),
            ]));
    }

    /**
     * Rückgang: kein Zuschlag — Uhren-Status wird wiederhergestellt.
     */
    private static function unsoldAction(): Action
    {
        return Action::make('settleUnsold')
            ->label('Rückgang')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (AuctionLot $lot): bool => $lot->isOpen()
                && ! $lot->trashed()
                && (auth()->user()?->can('auctions.update') ?? false))
            ->requiresConfirmation()
            ->modalHeading(fn (AuctionLot $lot): string => 'Rückgang für Los '.$lot->lot_code)
            ->modalDescription('Kein Zuschlag — die Uhr kehrt in ihren vorherigen Bestandsstatus zurück.')
            ->modalSubmitActionLabel('Rückgang erfassen')
            ->action(function (AuctionLot $lot): void {
                app(SettleLotAction::class)->unsold($lot);

                Notification::make()
                    ->success()
                    ->title('Rückgang erfasst')
                    ->body('Die Uhr ist zurück im vorherigen Bestandsstatus.')
                    ->send();
            });
    }

    /**
     * Rückzug: Los vor dem Aufruf entnommen — Status-Restore.
     */
    private static function withdrawAction(): Action
    {
        return Action::make('withdrawLot')
            ->label('Zurückziehen')
            ->icon('heroicon-m-x-circle')
            ->color('gray')
            ->visible(fn (AuctionLot $lot): bool => $lot->isOpen()
                && ! $lot->trashed()
                && (auth()->user()?->can('auctions.update') ?? false))
            ->requiresConfirmation()
            ->modalHeading(fn (AuctionLot $lot): string => 'Los '.$lot->lot_code.' zurückziehen')
            ->modalDescription('Das Los wird aus der Auktion genommen — die Uhr kehrt in ihren vorherigen Bestandsstatus zurück.')
            ->modalSubmitActionLabel('Zurückziehen')
            ->action(function (AuctionLot $lot): void {
                app(SettleLotAction::class)->withdraw($lot);

                Notification::make()
                    ->success()
                    ->title('Los zurückgezogen')
                    ->body('Die Uhr ist zurück im vorherigen Bestandsstatus.')
                    ->send();
            });
    }
}
