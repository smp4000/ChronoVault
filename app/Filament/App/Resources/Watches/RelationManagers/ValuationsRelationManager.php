<?php

/**
 * =========================================================================
 * ValuationsRelationManager — Wertentwicklung direkt an der Uhr
 * =========================================================================
 *
 * Zweck:
 *   Bewertungs-Historie (KI-Recherchen + manuelle Einschätzungen) auf
 *   der Uhren-Bearbeitungsseite. Manuelle Bewertungen laufen über die
 *   RecordValuationAction (hält current_market_value synchron);
 *   Bewertungen sind Historie — bewusst KEINE Edit-Aktion (löschen +
 *   neu erfassen statt nachträglich verbiegen).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches\RelationManagers;

use App\Actions\Valuations\RecordValuationAction;
use App\Enums\ValuationSource;
use App\Models\Valuation;
use App\Models\Watch;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ValuationsRelationManager extends RelationManager
{
    protected static string $relationship = 'valuations';

    protected static ?string $title = 'Wertentwicklung';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('market_value')
                ->label('Marktwert')
                ->numeric()
                ->minValue(0)
                ->required()
                ->prefix('€'),

            DatePicker::make('valued_at')
                ->label('Bewertet am')
                ->default(now())
                ->maxDate(now())
                ->required(),

            Textarea::make('notes')
                ->label('Notizen')
                ->rows(2)
                ->placeholder('z. B. Einschätzung des Konzessionärs'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel('Bewertung')
            ->pluralModelLabel('Bewertungen')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([SoftDeletingScope::class]))
            ->columns([
                TextColumn::make('valued_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Quelle')
                    ->badge(),

                TextColumn::make('market_value')
                    ->label('Marktwert')
                    ->money('EUR')
                    ->weight('semibold')
                    ->alignEnd(),

                TextColumn::make('value_low')
                    ->label('Spanne')
                    ->state(fn (Valuation $record): string => $record->value_low !== null && $record->value_high !== null
                        ? number_format((float) $record->value_low, 0, ',', '.').' – '.number_format((float) $record->value_high, 0, ',', '.').' €'
                        : '—'),

                TextColumn::make('summary')
                    ->label('Einschätzung')
                    ->limit(60)
                    ->tooltip(fn (Valuation $record): ?string => $record->summary)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('valued_at', 'desc')
            ->filters([
                TrashedFilter::make()->label('Papierkorb'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Manuelle Bewertung')
                    ->using(function (array $data): Valuation {
                        /** @var Watch $watch */
                        $watch = $this->getOwnerRecord();

                        return app(RecordValuationAction::class)->execute($watch, [
                            ...$data,
                            'source' => ValuationSource::Manual->value,
                        ]);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading('Bewertung löschen')
                    ->successNotificationTitle('Bewertung gelöscht'),

                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->emptyStateHeading('Noch keine Bewertungen')
            ->emptyStateDescription('KI-Marktrecherchen und manuelle Einschätzungen bilden hier die Wertentwicklung.')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }
}
