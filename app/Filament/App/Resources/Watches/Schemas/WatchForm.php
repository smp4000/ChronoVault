<?php

/**
 * =========================================================================
 * WatchForm — Formular-Schema des Uhrenbestands (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Anlage & Bearbeitung von Uhren mit Stammdaten-Verknüpfung.
 *
 * Abhängiges Kaliber-Feld:
 *   Die Kaliber-Auswahl zeigt nur Werke der gewählten Marke — die
 *   Marken-Auswahl ist deshalb live() und setzt das Kaliber beim
 *   Markenwechsel zurück (sonst bliebe ein fremdes Kaliber gespeichert).
 *
 * Auswahlfelder & is_active:
 *   Wie im CaliberForm zeigen die Selects nur aktive Stammdaten — plus
 *   den aktuell zugewiesenen Wert beim Bearbeiten (Bestandsschutz).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches\Schemas;

use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Models\Watch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class WatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Uhr')
                    ->icon('heroicon-m-clock')
                    ->columns(2)
                    ->components([
                        Select::make('brand_id')
                            ->label('Marke')
                            ->relationship(
                                name: 'brand',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, ?Watch $record): Builder => $query
                                    ->where('is_active', true)
                                    ->when(
                                        $record?->brand_id,
                                        fn (Builder $q, string $brandId): Builder => $q->orWhere('id', $brandId),
                                    ),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            // Markenwechsel: fremdes Kaliber zurücksetzen.
                            ->afterStateUpdated(fn (Set $set) => $set('caliber_id', null)),

                        Select::make('caliber_id')
                            ->label('Kaliber')
                            ->relationship(
                                name: 'caliber',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get, ?Watch $record): Builder => $query
                                    ->where('brand_id', $get('brand_id'))
                                    ->where(fn (Builder $q): Builder => $q
                                        ->where('is_active', true)
                                        ->when(
                                            $record?->caliber_id,
                                            fn (Builder $qq, string $caliberId): Builder => $qq->orWhere('id', $caliberId),
                                        )),
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => blank($get('brand_id')))
                            ->helperText('Optional — Auswahl erscheint nach Wahl der Marke.'),

                        TextInput::make('model_name')
                            ->label('Modell')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('z. B. Submariner Date'),

                        TextInput::make('reference_number')
                            ->label('Referenznummer')
                            ->maxLength(255)
                            ->placeholder('z. B. 126610LN'),

                        TextInput::make('serial_number')
                            ->label('Seriennummer')
                            ->maxLength(255),

                        TextInput::make('stock_number')
                            ->label('Bestandsnummer')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Interne Nummer Ihres Betriebs — muss eindeutig sein.'),

                        TextInput::make('production_year')
                            ->label('Baujahr')
                            ->numeric()
                            ->minValue(1700)
                            ->maxValue(now()->year),
                    ]),

                Section::make('Zustand & Status')
                    ->icon('heroicon-m-shield-check')
                    ->columns(2)
                    ->components([
                        Select::make('condition')
                            ->label('Zustand')
                            ->options(WatchCondition::class)
                            ->required(),

                        Select::make('status')
                            ->label('Bestandsstatus')
                            ->options(WatchStatus::class)
                            ->default(WatchStatus::InStock)
                            ->required(),

                        Toggle::make('has_box')
                            ->label('Box vorhanden'),

                        Toggle::make('has_papers')
                            ->label('Papiere vorhanden'),
                    ]),

                Section::make('Gehäuse & Ausstattung')
                    ->icon('heroicon-m-squares-2x2')
                    ->columns(2)
                    ->collapsible()
                    ->components([
                        TextInput::make('case_material')
                            ->label('Gehäusematerial')
                            ->maxLength(255)
                            ->placeholder('z. B. Edelstahl, Gelbgold 18k'),

                        TextInput::make('case_diameter_mm')
                            ->label('Gehäusedurchmesser')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.1)
                            ->suffix('mm'),

                        TextInput::make('dial_color')
                            ->label('Zifferblatt')
                            ->maxLength(255)
                            ->placeholder('z. B. Schwarz, Sunburst-Blau'),

                        TextInput::make('bracelet_material')
                            ->label('Band')
                            ->maxLength(255)
                            ->placeholder('z. B. Oyster-Band Edelstahl, Leder'),

                        Textarea::make('notes')
                            ->label('Interne Notizen')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Nur für Ihr Team sichtbar.'),
                    ]),
            ]);
    }
}
