<?php

/**
 * =========================================================================
 * UserForm — Formular-Schema der Benutzerverwaltung (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Anlage & Bearbeitung von Mitarbeitern inkl. Rollenzuweisung
 *   (spatie/laravel-permission, Rollen liegen in der Tenant-DB).
 *
 * Passwort-Logik:
 *   - Beim Anlegen: Pflichtfeld.
 *   - Beim Bearbeiten: optional — nur wenn ausgefüllt, wird es gesetzt
 *     (dehydrated nur bei filled). Das Hashing übernimmt der
 *     'hashed'-Cast im User-Model.
 *
 * Rollen-Anzeige:
 *   Rollennamen sind englische Code-Werte (owner, admin, …) — die UI
 *   zeigt die deutschen Labels aus dem UserRole-Enum.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Benutzerdaten')
                    ->icon('heroicon-m-user')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-Mail-Adresse')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            // Eindeutigkeit gilt automatisch nur innerhalb der
                            // Tenant-DB — genau das gewünschte Verhalten.
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label('Passwort')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            // Pflicht nur beim Anlegen; beim Bearbeiten optional.
                            ->required(fn (string $operation): bool => $operation === 'create')
                            // Nur speichern, wenn tatsächlich ein neues Passwort eingegeben wurde.
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Beim Bearbeiten leer lassen, um das Passwort beizubehalten.'),

                        Select::make('roles')
                            ->label('Rollen')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->required()
                            // Deutsche Labels für die englischen Rollen-Codes.
                            ->getOptionLabelFromRecordUsing(
                                fn (Role $record): string => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name
                            )
                            ->helperText('Inhaber können alles, Administratoren verwalten Benutzer, Mitarbeiter arbeiten operativ, Betrachter haben Nur-Lese-Zugriff.'),
                    ]),
            ]);
    }
}
