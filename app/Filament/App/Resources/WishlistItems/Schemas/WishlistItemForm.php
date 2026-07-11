<?php

/**
 * =========================================================================
 * WishlistItemForm — Formular eines Wunschmodells
 * =========================================================================
 * Marke/Modell/Referenz + Zielpreis und Status. Die Marktwert-Felder
 * pflegt die Bewertung — bewusst nicht editierbar.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\WishlistItems\Schemas;

use App\Enums\WishlistStatus;
use App\Models\Brand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WishlistItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Wunschmodell')
                    ->description('Welche Uhr suchen Sie — und zu welchem Preis würden Sie zuschlagen?')
                    ->icon('heroicon-m-heart')
                    ->columns(2)
                    ->components([
                        Select::make('brand_id')
                            ->label('Marke')
                            ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),

                        TextInput::make('model_name')
                            ->label('Modell')
                            ->placeholder('z. B. Submariner Date')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('reference_number')
                            ->label('Referenznummer')
                            ->placeholder('z. B. 126610LN')
                            ->maxLength(100)
                            ->helperText('Mit Referenz wird die Marktrecherche deutlich treffsicherer.'),

                        TextInput::make('target_price')
                            ->label('Zielpreis')
                            ->numeric()
                            ->minValue(1)
                            ->prefix('€')
                            ->helperText('Fällt der Marktwert auf oder unter diesen Wert, kommt eine Alarm-Mail.'),

                        Select::make('status')
                            ->label('Status')
                            ->options(WishlistStatus::class)
                            ->default(WishlistStatus::Active->value)
                            ->required()
                            ->native(false),

                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('z. B. gewünschter Zustand, Full Set, bestimmte Zifferblatt-Variante …'),
                    ]),
            ]);
    }
}
