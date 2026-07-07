<?php

/**
 * =========================================================================
 * CaliberForm — Formular-Schema der Kaliber-Stammdaten (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Anlage & Bearbeitung von Kalibern. Wird von der CaliberResource UND
 *   dem CalibersRelationManager (BrandResource) genutzt — im Relation-
 *   Manager-Kontext steht der Hersteller bereits fest, deshalb blendet
 *   `withBrand: false` das Hersteller-Feld aus.
 *
 * Validierung:
 *   - name ist nur INNERHALB der Marke eindeutig (DB: unique brand_id+name).
 *     Die brand_id für die Unique-Rule kommt je nach Kontext aus dem
 *     Formularfeld ODER vom Owner-Record des Relation Managers.
 *
 * Hersteller-Auswahl:
 *   Nur aktive Marken — plus der aktuell zugewiesenen Marke beim
 *   Bearbeiten (sonst würde ein Kaliber einer inaktiven Marke sein
 *   Hersteller-Feld "verlieren").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Calibers\Schemas;

use App\Enums\MovementType;
use App\Models\Caliber;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;

class CaliberForm
{
    public static function configure(Schema $schema, bool $withBrand = true): Schema
    {
        return $schema
            ->components([
                Section::make('Kaliberdaten')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->columns(2)
                    ->components(array_filter([
                        $withBrand
                            ? Select::make('brand_id')
                                ->label('Hersteller')
                                ->relationship(
                                    name: 'brand',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query, ?Caliber $record): Builder => $query
                                        ->where('is_active', true)
                                        ->when(
                                            $record?->brand_id,
                                            fn (Builder $q, string $brandId): Builder => $q->orWhere('id', $brandId),
                                        ),
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                            : null,

                        TextInput::make('name')
                            ->label('Bezeichnung')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('z. B. 3235 oder El Primero 3600')
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule, Get $get, Component $livewire): Unique {
                                    // Kontextabhängige brand_id: Formularfeld (CaliberResource)
                                    // oder Owner-Record (CalibersRelationManager).
                                    $brandId = $get('brand_id') ?? (
                                        $livewire instanceof RelationManager
                                            ? $livewire->getOwnerRecord()->getKey()
                                            : null
                                    );

                                    return $rule->where('brand_id', $brandId);
                                },
                            )
                            ->validationMessages([
                                'unique' => 'Dieses Kaliber existiert für den Hersteller bereits.',
                            ]),

                        Select::make('movement_type')
                            ->label('Werktyp')
                            ->options(MovementType::class)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Aktiv')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Inaktive Kaliber erscheinen nicht in Auswahlfeldern neuer Datensätze.'),
                    ])),

                Section::make('Technische Kenndaten')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->columns(2)
                    ->collapsible()
                    ->components([
                        TextInput::make('power_reserve_hours')
                            ->label('Gangreserve')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5000)
                            ->suffix('Std.'),

                        TextInput::make('frequency_vph')
                            ->label('Frequenz')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('A/h')
                            ->helperText('Halbschwingungen pro Stunde, z. B. 28.800.'),

                        TextInput::make('jewels')
                            ->label('Steine (Jewels)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(255),

                        TextInput::make('diameter_mm')
                            ->label('Durchmesser')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.1)
                            ->suffix('mm'),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
