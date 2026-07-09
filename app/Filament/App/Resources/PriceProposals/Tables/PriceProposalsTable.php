<?php

/**
 * =========================================================================
 * PriceProposalsTable — Tabellen-Definition der Preisvorschläge
 * =========================================================================
 *
 * Zweck:
 *   Alle Vorschläge mit Uhr, Wunschpreis vs. Angebotspreis, Kunde und
 *   Status. Aktionen:
 *   - Annehmen  → AcceptPriceProposalAction (Zuschlag: Verkauf zum
 *     Wunschpreis, Rechnung + Kaufvertrag per Mail an den Kunden)
 *   - Gegenangebot → CounterPriceProposalAction (Mail mit Händler-Preis)
 *   - Ablehnen  → reiner Statuswechsel
 *   - Antworten → mailto mit vorbereitetem Betreff
 *   Kein Editieren der Kundendaten (Beweiswert).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\PriceProposals\Tables;

use App\Actions\Shop\AcceptPriceProposalAction;
use App\Actions\Shop\CounterPriceProposalAction;
use App\Enums\PriceProposalStatus;
use App\Models\PriceProposal;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class PriceProposalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('watch.brand'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Eingegangen')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('watch.model_name')
                    ->label('Uhr')
                    ->state(fn (PriceProposal $record): string => $record->watch?->fullName() ?? '—')
                    ->weight('semibold')
                    ->searchable(),

                TextColumn::make('proposed_price')
                    ->label('Wunschpreis')
                    ->money('EUR')
                    ->weight('bold')
                    ->color('primary')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('counter_price')
                    ->label('Gegenangebot')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('asking_price_at_time')
                    ->label('Angebotspreis')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd()
                    ->description(function (PriceProposal $record): ?string {
                        $asking = $record->getAttribute('asking_price_at_time');
                        $proposed = $record->getAttribute('proposed_price');

                        if ($asking === null || (float) $asking <= 0) {
                            return null;
                        }

                        $percent = (int) round((1 - (float) $proposed / (float) $asking) * 100);

                        return $percent > 0 ? '−'.$percent.' % unter Angebot' : null;
                    }),

                TextColumn::make('name')
                    ->label('Kunde')
                    ->description(fn (PriceProposal $record): string => $record->email)
                    ->searchable(),

                TextColumn::make('message')
                    ->label('Nachricht')
                    ->limit(40)
                    ->placeholder('—')
                    ->tooltip(fn (PriceProposal $record): ?string => $record->message)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PriceProposalStatus::class),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Antworten')
                    ->icon('heroicon-m-envelope')
                    ->color('gray')
                    ->url(fn (PriceProposal $record): string => 'mailto:'.$record->email
                        .'?subject='.rawurlencode('Ihr Preisvorschlag zu '.($record->watch?->fullName() ?? 'unserer Uhr'))),

                self::acceptAction(),
                self::counterAction(),
                self::declineAction(),

                DeleteAction::make()
                    ->modalHeading('Preisvorschlag löschen')
                    ->successNotificationTitle('Preisvorschlag gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Preisvorschlag wiederhergestellt'),
            ])
            ->emptyStateHeading('Keine Preisvorschläge')
            ->emptyStateDescription('Preisvorschläge von der Shop-Detailseite erscheinen hier — zusätzlich zur E-Mail-Benachrichtigung.')
            ->emptyStateIcon('heroicon-o-currency-euro');
    }

    /**
     * Sichtbarkeit der Bearbeitungs-Aktionen: nur offene Vorschläge
     * (Neu/Gegenangebot), nicht gelöscht, mit watches.update-Recht.
     */
    private static function actionVisible(PriceProposal $record): bool
    {
        $status = $record->getAttribute('status');

        return $status instanceof PriceProposalStatus
            && $status->isOpen()
            && ! $record->trashed()
            && (auth()->user()?->can('watches.update') ?? false);
    }

    /**
     * Annehmen = Zuschlag: Verkauf zum Wunschpreis, Rechnung + Kaufvertrag
     * gehen automatisch per Mail an den Kunden (AcceptPriceProposalAction).
     * Optionale Lieferadresse für die Rechnung, falls bereits bekannt.
     */
    private static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('Annehmen')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn (PriceProposal $record): bool => self::actionVisible($record))
            ->modalHeading('Preisvorschlag annehmen — Zuschlag erteilen')
            ->modalDescription(fn (PriceProposal $record): string => 'Die Uhr wird verbindlich zum Wunschpreis von '
                .number_format((float) $record->proposed_price, 2, ',', '.')
                .' € verkauft. Der Kunde erhält die Zusage mit Zahlungsinformationen, Rechnung und Kaufvertrag per E-Mail.')
            ->modalSubmitActionLabel('Zuschlag erteilen')
            ->form([
                TextInput::make('street')
                    ->label('Straße und Hausnummer (falls bekannt)')
                    ->maxLength(255),

                TextInput::make('postal_code')
                    ->label('PLZ')
                    ->maxLength(20),

                TextInput::make('city')
                    ->label('Ort')
                    ->maxLength(255),
            ])
            ->action(function (PriceProposal $record, array $data): void {
                try {
                    app(AcceptPriceProposalAction::class)->execute($record, $data);
                } catch (RuntimeException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Zuschlag erteilt')
                    ->body('Verkauf erfasst — der Kunde erhält Zusage, Rechnung und Kaufvertrag per E-Mail.')
                    ->send();
            });
    }

    /**
     * Gegenangebot: Händler-Preis + optionale Nachricht per Mail an den
     * Kunden (CounterPriceProposalAction, Status → Gegenangebot).
     */
    private static function counterAction(): Action
    {
        return Action::make('counter')
            ->label('Gegenangebot')
            ->icon('heroicon-m-arrows-right-left')
            ->color('info')
            ->visible(fn (PriceProposal $record): bool => self::actionVisible($record))
            ->modalHeading('Gegenangebot senden')
            ->modalSubmitActionLabel('Gegenangebot senden')
            ->form(fn (PriceProposal $record): array => [
                TextInput::make('counter_price')
                    ->label('Ihr Angebot')
                    ->numeric()
                    ->minValue(1)
                    ->prefix('€')
                    ->required()
                    ->default($record->getAttribute('asking_price_at_time')
                        ? (int) round(((float) $record->proposed_price + (float) $record->asking_price_at_time) / 2)
                        : null)
                    ->helperText('Vorschlag des Kunden: '.number_format((float) $record->proposed_price, 0, ',', '.').' €'),

                Textarea::make('message')
                    ->label('Nachricht an den Kunden (optional)')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (PriceProposal $record, array $data): void {
                try {
                    app(CounterPriceProposalAction::class)->execute(
                        $record,
                        (float) $data['counter_price'],
                        filled($data['message'] ?? null) ? (string) $data['message'] : null,
                    );
                } catch (RuntimeException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Gegenangebot gesendet')
                    ->body('Der Kunde wurde per E-Mail informiert — seine Antwort landet direkt bei Ihnen.')
                    ->send();
            });
    }

    /**
     * Ablehnen: reiner Statuswechsel — die Absage formuliert der
     * Händler bei Bedarf selbst über „Antworten".
     */
    private static function declineAction(): Action
    {
        return Action::make('decline')
            ->label('Ablehnen')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->visible(fn (PriceProposal $record): bool => self::actionVisible($record))
            ->requiresConfirmation()
            ->modalHeading('Preisvorschlag ablehnen')
            ->modalDescription('Der Status wird auf „Abgelehnt" gesetzt — eine Absage an den Kunden senden Sie bei Bedarf über „Antworten".')
            ->action(function (PriceProposal $record): void {
                $record->update(['status' => PriceProposalStatus::Declined]);

                Notification::make()
                    ->success()
                    ->title('Preisvorschlag abgelehnt')
                    ->send();
            });
    }
}
