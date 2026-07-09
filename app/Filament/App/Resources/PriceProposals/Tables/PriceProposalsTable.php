<?php

/**
 * =========================================================================
 * PriceProposalsTable — Tabellen-Definition der Preisvorschläge
 * =========================================================================
 *
 * Zweck:
 *   Alle Vorschläge mit Uhr, Wunschpreis vs. Angebotspreis, Kunde und
 *   Status. Aktionen: Annehmen/Ablehnen (Status), Antworten (mailto mit
 *   vorbereitetem Betreff — der Mail-Dialog des Händlers öffnet sich).
 *   Nur Statuswechsel, kein Editieren der Kundendaten (Beweiswert).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\PriceProposals\Tables;

use App\Enums\PriceProposalStatus;
use App\Models\PriceProposal;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                self::statusAction('accept', 'Annehmen', PriceProposalStatus::Accepted, 'heroicon-m-check-circle', 'success'),
                self::statusAction('decline', 'Ablehnen', PriceProposalStatus::Declined, 'heroicon-m-x-circle', 'danger'),

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
     * Statuswechsel-Aktion (Annehmen/Ablehnen) — reine Statusänderung,
     * die Antwort an den Kunden schreibt der Händler per Mail.
     */
    private static function statusAction(
        string $name,
        string $label,
        PriceProposalStatus $target,
        string $icon,
        string $color,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (PriceProposal $record): bool => $record->getAttribute('status') === PriceProposalStatus::New
                && ! $record->trashed()
                && (auth()->user()?->can('watches.update') ?? false))
            ->requiresConfirmation()
            ->modalHeading('Preisvorschlag '.strtolower($label))
            ->modalDescription('Der Status wird geändert — die Antwort an den Kunden senden Sie über „Antworten" per E-Mail.')
            ->action(function (PriceProposal $record) use ($target, $label): void {
                $record->update(['status' => $target]);

                Notification::make()
                    ->success()
                    ->title('Preisvorschlag '.($label === 'Annehmen' ? 'angenommen' : 'abgelehnt'))
                    ->send();
            });
    }
}
