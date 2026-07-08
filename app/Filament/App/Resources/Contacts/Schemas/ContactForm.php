<?php

/**
 * =========================================================================
 * ContactForm — Formular-Schema des Kundenstamms (Tenant-Panel)
 * =========================================================================
 *
 * Validierung:
 *   Firma ODER Nachname muss gefüllt sein (requiredWithout) — Kontakte
 *   sind Privatpersonen oder Firmen.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Contacts\Schemas;

use App\Enums\ContactType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kontakt')
                    ->icon('heroicon-m-user')
                    ->columns(2)
                    ->components([
                        Select::make('type')
                            ->label('Art')
                            ->options(ContactType::class)
                            ->default(ContactType::PrivatePerson)
                            ->required(),

                        TextInput::make('company_name')
                            ->label('Firma')
                            ->maxLength(255)
                            ->requiredWithout('last_name')
                            ->validationMessages(['required_without' => 'Firma oder Nachname angeben.']),

                        TextInput::make('first_name')
                            ->label('Vorname')
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label('Nachname')
                            ->maxLength(255)
                            ->requiredWithout('company_name')
                            ->validationMessages(['required_without' => 'Firma oder Nachname angeben.']),

                        TextInput::make('email')
                            ->label('E-Mail-Adresse')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(255),
                    ]),

                Section::make('Adresse')
                    ->icon('heroicon-m-map-pin')
                    ->columns(2)
                    ->collapsible()
                    ->components([
                        TextInput::make('street')
                            ->label('Straße & Hausnummer')
                            ->maxLength(255),

                        TextInput::make('postal_code')
                            ->label('PLZ')
                            ->maxLength(20),

                        TextInput::make('city')
                            ->label('Ort')
                            ->maxLength(255),

                        TextInput::make('country')
                            ->label('Land')
                            ->maxLength(255)
                            ->placeholder('z. B. Deutschland'),

                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
