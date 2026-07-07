<?php

/**
 * =========================================================================
 * BrandForm — Formular-Schema der Marken-Stammdaten (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Anlage & Bearbeitung von Uhrenmarken/Werkherstellern.
 *
 * Validierung:
 *   - name unique: Die Prüfung schließt soft-gelöschte Marken EIN
 *     (bewusst — der DB-Unique-Index kennt kein SoftDelete). Gelöschte
 *     Marken lassen sich über den Papierkorb-Filter wiederherstellen.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Brands\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Markendaten')
                    ->icon('heroicon-m-tag')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('country')
                            ->label('Land')
                            ->maxLength(255)
                            ->placeholder('z. B. Schweiz'),

                        TextInput::make('founded_year')
                            ->label('Gründungsjahr')
                            ->numeric()
                            ->minValue(1500)
                            ->maxValue(now()->year),

                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://…'),

                        SpatieMediaLibraryFileUpload::make('logo')
                            ->label('Logo')
                            ->collection('logo')
                            ->image()
                            ->maxSize(5120)
                            ->helperText('Ein Bild — ein neuer Upload ersetzt das vorhandene Logo.')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(4)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktiv')
                            ->default(true)
                            ->helperText('Inaktive Marken erscheinen nicht in Auswahlfeldern neuer Datensätze.'),
                    ]),
            ]);
    }
}
