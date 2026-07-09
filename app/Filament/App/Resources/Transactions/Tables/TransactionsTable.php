<?php

/**
 * =========================================================================
 * TransactionsTable — Tabellen-Definition der Kauf-/Verkaufsbelege
 * =========================================================================
 *
 * Zweck:
 *   Wird von der TransactionResource UND dem TransactionsRelationManager
 *   genutzt — `withWatch: false` blendet die Uhren-Spalte aus (im
 *   Relation Manager ist die Uhr der Kontext).
 *
 * WARUM keine Bulk-Löschaktion: Konsistenz (Policy-Checks pro Datensatz).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Transactions\Tables;

use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionsTable
{
    public static function configure(Table $table, bool $withWatch = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $withWatch
                ? $query->with(['watch.brand', 'contact'])
                : $query->with('contact'))
            ->columns(array_filter([
                TextColumn::make('transacted_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Art')
                    ->badge(),

                $withWatch
                    ? TextColumn::make('watch.model_name')
                        ->label('Uhr')
                        ->state(fn (Transaction $record): string => $record->watch->fullName())
                        ->searchable(query: fn (Builder $query, string $search): Builder => $query
                            ->whereHas('watch', fn (Builder $q) => $q
                                ->where('model_name', 'like', "%{$search}%")
                                ->orWhere('reference_number', 'like', "%{$search}%")))
                        ->weight('semibold')
                    : null,

                TextColumn::make('contact.last_name')
                    ->label('Kontakt')
                    ->state(fn (Transaction $record): string => $record->contact?->displayName() ?? '—'),

                TextColumn::make('price')
                    ->label('Preis')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('payment_method')
                    ->label('Zahlungsart')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('document_number')
                    ->label('Belegnr.')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
            ]))
            ->defaultSort('transacted_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Art')
                    ->options(TransactionType::class),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                self::documentAction('invoicePdf', 'Rechnung', 'heroicon-m-document-text',
                    fn (InvoiceService $service, $invoice): string => $service->renderZugferdPdf($invoice),
                    fn ($invoice): string => $invoice->invoice_number.'.pdf'),

                self::documentAction('contractPdf', 'Kaufvertrag', 'heroicon-m-document-check',
                    fn (InvoiceService $service, $invoice): string => $service->renderContractPdf($invoice),
                    fn ($invoice): string => 'Kaufvertrag-'.$invoice->invoice_number.'.pdf'),

                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Beleg stornieren (Papierkorb)')
                    ->successNotificationTitle('Beleg storniert'),

                RestoreAction::make()
                    ->successNotificationTitle('Beleg wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Beleg endgültig löschen')
                    ->successNotificationTitle('Beleg endgültig gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Belege')
            ->emptyStateDescription('An- und Verkäufe erscheinen hier als Preishistorie.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    /**
     * PDF-Download (Rechnung als ZUGFeRD-E-Rechnung bzw. Kaufvertrag).
     * Die Rechnung wird beim ersten Abruf erstellt (Nummernkreis!);
     * Guards des InvoiceService erscheinen als Danger-Notification.
     *
     * @param  callable(InvoiceService, Invoice): string  $render
     * @param  callable(Invoice): string  $filename
     */
    private static function documentAction(string $name, string $label, string $icon, callable $render, callable $filename): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color('gray')
            ->visible(fn (Transaction $record): bool => $record->getAttribute('type') === TransactionType::Sale
                && ! $record->trashed()
                && (auth()->user()?->can('transactions.view') ?? false))
            ->action(function (Transaction $record) use ($render, $filename): ?StreamedResponse {
                $service = app(InvoiceService::class);

                try {
                    $invoice = $service->getOrCreateForSale($record);
                    $content = $render($service, $invoice);
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Dokument kann nicht erstellt werden')
                        ->body($exception->getMessage())
                        ->send();

                    return null;
                }

                return response()->streamDownload(
                    fn () => print ($content),
                    $filename($invoice),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }
}
