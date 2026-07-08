<?php

/**
 * =========================================================================
 * AuctionForm — Formular-Schema der Auktionen
 * =========================================================================
 *
 * Zweck:
 *   Stammdaten des Auktions-Ereignisses. Der STATUS ist bewusst ein
 *   normales Auswahlfeld (kein Workflow-Zwang) — Auktionshäuser pflegen
 *   den Ablauf selbst; die harten Regeln (Einliefern nur solange die
 *   Auktion Lose annimmt, Löschen nur ohne offene Lose) liegen in
 *   Action und Policy.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Auctions\Schemas;

use App\Enums\AuctionStatus;
use App\Enums\AuctionVenue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Auktion')
                    ->icon('heroicon-m-megaphone')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label('Titel')
                            ->placeholder('z. B. „Herbstauktion 2026 — Armbanduhren"')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('venue')
                            ->label('Austragungsform')
                            ->options(AuctionVenue::class)
                            ->default(AuctionVenue::Saleroom)
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options(AuctionStatus::class)
                            ->default(AuctionStatus::Draft)
                            ->required()
                            ->helperText('Lose annehmen: Entwurf, Geplant oder Läuft.'),

                        TextInput::make('location')
                            ->label('Ort')
                            ->placeholder('Saal, Adresse oder Plattform')
                            ->maxLength(255),

                        DateTimePicker::make('starts_at')
                            ->label('Beginn')
                            ->seconds(false),

                        DateTimePicker::make('ends_at')
                            ->label('Ende')
                            ->seconds(false)
                            ->after('starts_at'),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Katalogtext, Besichtigungstermine, Hinweise …'),

                        Textarea::make('notes')
                            ->label('Interne Notizen')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
