<?php

/**
 * =========================================================================
 * WatchesTable — Tabellen-Definition des Uhrenbestands (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Bestandsliste mit Status-/Zustands-Badges (deutsche Labels aus den
 *   Enums), Full-Set-Indikator und Papierkorb-Filter. Autorisierung
 *   übernimmt die WatchPolicy — hier steht KEINE eigene Logik.
 *
 * WARUM keine Bulk-Löschaktion:
 *   Konsistenz mit allen bisherigen Tabellen (Policy-Checks pro Datensatz).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches\Tables;

use App\Actions\Services\StartServiceAction;
use App\Actions\Transactions\RecordSaleAction;
use App\Actions\Valuations\RecordValuationAction;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use App\Enums\ValuationSource;
use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Filament\App\Resources\ServiceRecords\Schemas\ServiceRecordForm;
use App\Filament\App\Resources\Transactions\Schemas\TransactionForm;
use App\Models\Watch;
use App\Services\MarketValueLookupService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Throwable;

class WatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager Loading der Marke — verhindert N+1 in der Spalte.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('brand'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('')
                    ->collection('photos')
                    ->limit(1)
                    ->imageSize(40)
                    ->extraImgAttributes(['style' => 'border-radius: 0.5rem; object-fit: cover;'])
                    ->toggleable(),

                TextColumn::make('stock_number')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('brand.name')
                    ->label('Marke')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('model_name')
                    ->label('Modell')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Watch $record): ?string => $record->reference_number),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('condition')
                    ->label('Zustand')
                    ->badge(),

                IconColumn::make('has_box')
                    ->label('Box')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('has_papers')
                    ->label('Papiere')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label('Shop')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('current_market_value')
                    ->label('Marktwert')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('production_year')
                    ->label('Baujahr')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('serial_number')
                    ->label('Seriennummer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Angelegt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Marke')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(WatchStatus::class)
                    ->multiple(),

                SelectFilter::make('condition')
                    ->label('Zustand')
                    ->options(WatchCondition::class)
                    ->multiple(),

                TernaryFilter::make('full_set')
                    ->label('Full Set')
                    ->placeholder('Alle Uhren')
                    ->trueLabel('Nur Full Set (Box & Papiere)')
                    ->falseLabel('Ohne vollständiges Set')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('has_box', true)->where('has_papers', true),
                        false: fn (Builder $query): Builder => $query->where(
                            fn (Builder $q): Builder => $q->where('has_box', false)->orWhere('has_papers', false)
                        ),
                    ),

                TrashedFilter::make()
                    ->label('Papierkorb'),
            ])
            ->recordActions([
                self::recordSaleAction(),
                self::startServiceAction(),
                self::marketValueAction(),

                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Uhr löschen')
                    ->successNotificationTitle('Uhr gelöscht'),

                RestoreAction::make()
                    ->successNotificationTitle('Uhr wiederhergestellt'),

                ForceDeleteAction::make()
                    ->modalHeading('Uhr endgültig löschen')
                    ->successNotificationTitle('Uhr endgültig gelöscht'),
            ])
            ->emptyStateHeading('Noch keine Uhren im Bestand')
            ->emptyStateDescription('Erfassen Sie Ihre erste Uhr — Marke und Kaliber stammen aus den Stammdaten.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * "Marktwert ermitteln"-Schnellaktion: KI-Recherche des aktuellen
     * Gebrauchtmarkt-Preises (Perplexity) → RecordValuationAction
     * (Historie + Schnellzugriff). Bestätigungsdialog, weil jeder
     * Abruf API-Guthaben kostet.
     */
    private static function marketValueAction(): Action
    {
        return Action::make('lookupMarketValue')
            ->label('Marktwert')
            ->icon('heroicon-m-chart-bar')
            ->color('warning')
            ->visible(fn (Watch $record): bool => ! $record->trashed()
                && (auth()->user()?->can('valuations.create') ?? false))
            ->requiresConfirmation()
            ->modalHeading(fn (Watch $record): string => 'Marktwert ermitteln: '.$record->fullName())
            ->modalDescription('Die KI recherchiert aktuelle Gebrauchtmarkt-Preise (berücksichtigt Zustand und Lieferumfang). Der Abruf dauert einige Sekunden und verbraucht API-Guthaben.')
            ->modalSubmitActionLabel('Recherchieren')
            ->action(function (Watch $record): void {
                try {
                    $data = app(MarketValueLookupService::class)->lookup($record);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('Marktwert-Recherche fehlgeschlagen')->body($e->getMessage())->send();

                    return;
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Marktwert-Recherche fehlgeschlagen')->body('Unerwarteter Fehler. Bitte später erneut versuchen.')->send();

                    return;
                }

                app(RecordValuationAction::class)->execute($record, [
                    'source' => ValuationSource::AiResearch->value,
                    'market_value' => $data->marketValue,
                    'value_low' => $data->valueLow,
                    'value_high' => $data->valueHigh,
                    'summary' => $data->summary,
                    'source_urls' => $data->sourceUrls,
                ]);

                $formatted = number_format($data->marketValue, 0, ',', '.');
                $body = "Aktueller Marktwert: {$formatted} €.";

                if ($record->purchase_price !== null) {
                    $delta = $data->marketValue - (float) $record->purchase_price;
                    $deltaFormatted = number_format(abs($delta), 0, ',', '.');
                    $body .= $delta >= 0
                        ? " Das sind {$deltaFormatted} € über dem Einkauf."
                        : " Das sind {$deltaFormatted} € unter dem Einkauf.";
                }

                Notification::make()
                    ->success()
                    ->title('Marktwert aktualisiert')
                    ->body($body)
                    ->send();
            });
    }

    /**
     * "In Service geben"-Schnellaktion: Modal mit Servicedaten, Start
     * über die StartServiceAction (merkt den aktuellen Status und setzt
     * "Im Service"). Sichtbar für Uhren, die weder verkauft noch bereits
     * im Service sind, und Benutzer mit services.create.
     */
    private static function startServiceAction(): Action
    {
        return Action::make('startService')
            ->label('In Service')
            ->icon('heroicon-m-wrench-screwdriver')
            ->color('info')
            ->visible(fn (Watch $record): bool => ! $record->isSold()
                && ! $record->isInService()
                && ! $record->trashed()
                && (auth()->user()?->can('services.create') ?? false))
            ->modalHeading(fn (Watch $record): string => 'In Service geben: '.$record->fullName())
            ->modalSubmitActionLabel('In Service geben')
            ->form([
                Select::make('type')
                    ->label('Art')
                    ->options(ServiceType::class)
                    ->default(ServiceType::FullService)
                    ->required(),

                ServiceRecordForm::workshopSelect(),

                DatePicker::make('submitted_at')
                    ->label('Eingereicht am')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),

                Textarea::make('description')
                    ->label('Beschreibung')
                    ->rows(2)
                    ->placeholder('Was ist zu tun?'),
            ])
            ->action(function (Watch $record, array $data): void {
                app(StartServiceAction::class)->execute($record, $data);

                Notification::make()
                    ->success()
                    ->title('Uhr im Service')
                    ->body('Der Vorgang ist angelegt; beim Abschluss kehrt die Uhr in den vorherigen Status zurück.')
                    ->send();
            });
    }

    /**
     * "Verkaufen"-Schnellaktion: Modal mit Verkaufsdaten, Erfassung über
     * die RecordSaleAction (setzt den Status auf Verkauft und liefert
     * die Marge für die Notification). Sichtbar nur für unverkaufte
     * Uhren und Benutzer mit transactions.create.
     */
    private static function recordSaleAction(): Action
    {
        return Action::make('recordSale')
            ->label('Verkaufen')
            ->icon('heroicon-m-banknotes')
            ->color('success')
            ->visible(fn (Watch $record): bool => ! $record->isSold()
                && ! $record->trashed()
                && (auth()->user()?->can('transactions.create') ?? false))
            ->modalHeading(fn (Watch $record): string => 'Verkauf erfassen: '.$record->fullName())
            ->modalSubmitActionLabel('Verkauf erfassen')
            ->form([
                TransactionForm::contactSelect()->label('Käufer'),

                TextInput::make('price')
                    ->label('Verkaufspreis')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('€'),

                DatePicker::make('transacted_at')
                    ->label('Datum')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),

                Select::make('payment_method')
                    ->label('Zahlungsart')
                    ->options(PaymentMethod::class),

                TextInput::make('document_number')
                    ->label('Belegnummer')
                    ->maxLength(255),

                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(2),
            ])
            ->action(function (Watch $record, array $data): void {
                $action = app(RecordSaleAction::class);
                $action->execute($record, $data);

                $margin = $action->margin($record, (float) $data['price']);
                $body = 'Die Uhr steht jetzt auf „Verkauft".';

                if ($margin !== null) {
                    $formatted = number_format(abs($margin), 2, ',', '.');
                    $body .= $margin >= 0
                        ? " Marge: {$formatted} €."
                        : " Verlust: {$formatted} €.";
                }

                Notification::make()
                    ->success()
                    ->title('Verkauf erfasst')
                    ->body($body)
                    ->send();
            });
    }
}
