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
use App\Actions\Shop\DeclinePriceProposalAction;
use App\Actions\Shop\SendProposalReplyAction;
use App\Enums\PriceProposalStatus;
use App\Models\PriceProposal;
use App\Services\ProposalReplyService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Throwable;

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
                self::replyAction(),

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
     * Antworten: Formular mit KI-Entwurf — Tenor + Stichpunkte wählen,
     * „KI-Entwurf erstellen" füllt die Nachricht (ProposalReplyService),
     * der Händler prüft/ändert und sendet (SendProposalReplyAction).
     * Bewusst KEINE Statusänderung.
     */
    private static function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Antworten')
            ->icon('heroicon-m-envelope')
            ->color('gray')
            ->visible(fn (PriceProposal $record): bool => ! $record->trashed()
                && (auth()->user()?->can('watches.update') ?? false))
            ->modalHeading(fn (PriceProposal $record): string => 'Antwort an '.$record->name)
            ->modalDescription(fn (PriceProposal $record): string => 'Wunschpreis '
                .number_format((float) $record->proposed_price, 0, ',', '.').' € für „'
                .($record->watch?->fullName() ?? 'unbekannte Uhr').'"'
                .(filled($record->message) ? ' — Kundennachricht: „'.str($record->message)->limit(120).'"' : ''))
            ->modalSubmitActionLabel('Antwort senden')
            ->modalWidth('2xl')
            ->form(fn (PriceProposal $record): array => [
                Select::make('tone')
                    ->label('Tenor der Antwort')
                    ->options(ProposalReplyService::TONES)
                    ->default('negotiate')
                    ->required()
                    ->native(false),

                TextInput::make('key_points')
                    ->label('Stichpunkte für die KI (optional)')
                    ->placeholder('z. B. Besichtigung in Fulda möglich, Service 2024 gemacht')
                    ->maxLength(500),

                TextInput::make('subject')
                    ->label('Betreff')
                    ->default('Ihr Preisvorschlag zu '.($record->watch?->fullName() ?? 'unserer Uhr'))
                    ->required()
                    ->maxLength(150),

                Textarea::make('message')
                    ->label('Nachricht')
                    ->rows(12)
                    ->required()
                    ->helperText('„KI-Entwurf erstellen" schreibt einen Vorschlag — Sie prüfen, passen an und senden.')
                    ->hintAction(
                        Action::make('generateDraft')
                            ->label('KI-Entwurf erstellen')
                            ->icon('heroicon-m-sparkles')
                            ->action(function (Get $get, Set $set, PriceProposal $record): void {
                                try {
                                    $draft = app(ProposalReplyService::class)->draft(
                                        $record,
                                        (string) ($get('tone') ?? 'negotiate'),
                                        filled($get('key_points')) ? (string) $get('key_points') : null,
                                    );
                                } catch (Throwable $exception) {
                                    report($exception);

                                    Notification::make()
                                        ->danger()
                                        ->title('KI-Entwurf fehlgeschlagen')
                                        ->body($exception->getMessage())
                                        ->send();

                                    return;
                                }

                                $set('message', $draft);
                            }),
                    ),
            ])
            ->action(function (PriceProposal $record, array $data): void {
                try {
                    app(SendProposalReplyAction::class)->execute(
                        $record,
                        (string) $data['subject'],
                        (string) $data['message'],
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title('Antwort konnte nicht gesendet werden')
                        ->body('Bitte Mail-Konfiguration prüfen — Details im Log.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Antwort gesendet')
                    ->body('Die Nachricht ist auf dem Weg an '.$record->email.'.')
                    ->send();
            });
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
            ->modalDescription(fn (PriceProposal $record): string => 'Die Uhr wird verbindlich zum Preis von '
                .number_format($record->counterTotal() ?? (float) $record->proposed_price, 2, ',', '.')
                .' € verkauft'
                .($record->counterTotal() !== null ? ' (Ihr Gegenangebot inkl. Versand)' : ' (Wunschpreis des Kunden)')
                .'. Der Kunde erhält die Zusage mit Zahlungsinformationen, Rechnung und Kaufvertrag per E-Mail.')
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
                // Läuft ein Gegenangebot, gilt dessen Gesamtpreis (inkl.
                // Versand) — sonst der Wunschpreis des Kunden.
                $total = $record->counterTotal();

                try {
                    app(AcceptPriceProposalAction::class)->execute(
                        $record,
                        $data,
                        $total,
                        $total !== null ? 'Gegenangebot angenommen (inkl. Versand).' : null,
                    );
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
            ->modalDescription('Die Mail enthält Annehmen-/Ablehnen-Buttons: Bei Annahme wird der Kauf automatisch abgewickelt (Verkauf, Rechnung, Kaufvertrag, Zahlungs-Mail), bei Ablehnung schließt sich der Vorgang.')
            ->modalSubmitActionLabel('Gegenangebot senden')
            ->modalWidth('2xl')
            ->form(fn (PriceProposal $record): array => [
                TextInput::make('counter_price')
                    ->label('Ihr Angebot für die Uhr')
                    ->numeric()
                    ->minValue(1)
                    ->prefix('€')
                    ->required()
                    ->default($record->getAttribute('asking_price_at_time')
                        ? (int) round(((float) $record->proposed_price + (float) $record->asking_price_at_time) / 2)
                        : null)
                    ->helperText('Vorschlag des Kunden: '.number_format((float) $record->proposed_price, 0, ',', '.').' €'),

                TextInput::make('shipping_price')
                    ->label('Porto & Verpackung (optional)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->helperText('Wird in der Mail separat ausgewiesen — der Kunde sieht Angebot + Versand = Gesamtpreis.'),

                Textarea::make('intro')
                    ->label('Ihr Text an den Kunden')
                    ->rows(5)
                    ->required()
                    ->maxLength(3000)
                    ->default('vielen Dank für Ihren Preisvorschlag über '
                        .number_format((float) $record->proposed_price, 0, ',', '.')
                        .' €. Ganz können wir Ihnen dabei leider nicht entgegenkommen — aber wir machen Ihnen gerne dieses Angebot:')
                    ->helperText('Frei anpassbar — die Anrede „Guten Tag '.$record->name.'," setzt die Mail automatisch davor.'),
            ])
            ->action(function (PriceProposal $record, array $data): void {
                try {
                    app(CounterPriceProposalAction::class)->execute(
                        $record,
                        (float) $data['counter_price'],
                        filled($data['shipping_price'] ?? null) ? (float) $data['shipping_price'] : null,
                        filled($data['intro'] ?? null) ? (string) $data['intro'] : null,
                    );
                } catch (RuntimeException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Gegenangebot gesendet')
                    ->body('Der Kunde kann per Klick annehmen (Kauf wird komplett abgewickelt) oder ablehnen.')
                    ->send();
            });
    }

    /**
     * Ablehnen: schließt den Vorgang UND schickt dem Kunden die
     * „Schade"-Mail — der Text ist frei editierbar (Vorlage vorbefüllt).
     */
    private static function declineAction(): Action
    {
        return Action::make('decline')
            ->label('Ablehnen')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->visible(fn (PriceProposal $record): bool => self::actionVisible($record))
            ->modalHeading('Preisvorschlag ablehnen')
            ->modalDescription('Der Vorgang wird geschlossen und der Kunde erhält die Absage per E-Mail.')
            ->modalSubmitActionLabel('Ablehnen & Mail senden')
            ->form(fn (PriceProposal $record): array => [
                Textarea::make('message')
                    ->label('Ihr Text an den Kunden')
                    ->rows(5)
                    ->required()
                    ->maxLength(3000)
                    ->default('schade, dass wir diesmal nicht zusammengekommen sind — vielen Dank trotzdem für Ihr Interesse! '
                        .'Unsere Kollektion wächst laufend: Schauen Sie gerne wieder vorbei, vielleicht ist bald genau das richtige Stück für Sie dabei.')
                    ->helperText('Frei anpassbar — die Anrede „Guten Tag '.$record->name.'," setzt die Mail automatisch davor.'),
            ])
            ->action(function (PriceProposal $record, array $data): void {
                try {
                    app(DeclinePriceProposalAction::class)->execute(
                        $record,
                        filled($data['message'] ?? null) ? (string) $data['message'] : null,
                    );
                } catch (RuntimeException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Preisvorschlag abgelehnt')
                    ->body('Die Absage ist per E-Mail an '.$record->email.' unterwegs.')
                    ->send();
            });
    }
}
