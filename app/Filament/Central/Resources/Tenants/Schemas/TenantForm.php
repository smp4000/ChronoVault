<?php

/**
 * =========================================================================
 * TenantForm — Formular-Schema der Mandantenverwaltung
 * =========================================================================
 *
 * Zweck:
 *   Definiert das Anlage-/Bearbeitungsformular für Mandanten. Beim
 *   ANLEGEN werden zusätzlich die Zugangsdaten des Inhabers abgefragt —
 *   diese Felder sind KEINE Model-Attribute; die CreateTenant-Page
 *   filtert sie aus den Formulardaten heraus und reicht sie an die
 *   CreateTenantAction durch.
 *
 * WARUM Inhaber-Felder im selben Formular:
 *   Ein Mandant ohne Owner-Zugang wäre unbenutzbar. Das Formular
 *   erzwingt, dass Provisioning immer vollständig passiert (kein
 *   „vergessener" zweiter Schritt).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Schemas;

use App\Enums\TenantStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Betrieb')
                    ->description('Stammdaten des Mandanten')
                    ->icon('heroicon-m-building-office-2')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Firmenname')
                            ->placeholder('z. B. Juwelier Müller GmbH')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('Subdomain')
                            ->placeholder('wird automatisch generiert')
                            ->helperText('Nur Kleinbuchstaben, Zahlen und Bindestriche. Leer lassen für automatische Generierung aus dem Firmennamen.')
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->maxLength(63)
                            // Nach dem Anlegen unveränderlich: Die Subdomain steckt in der
                            // registrierten Domain und in Login-Links der Benutzer.
                            ->disabledOn('edit'),

                        Select::make('status')
                            ->label('Status')
                            ->options(TenantStatus::class)
                            ->default(TenantStatus::Trial)
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Inhaber-Zugang')
                    ->description('Erster Benutzer des Mandanten — erhält die Rolle „Inhaber" mit allen Rechten.')
                    ->icon('heroicon-m-user-circle')
                    ->columns(2)
                    // Nur beim Anlegen: Benutzer werden danach im Tenant-Panel verwaltet.
                    ->visibleOn('create')
                    ->components([
                        TextInput::make('owner_name')
                            ->label('Name des Inhabers')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('owner_email')
                            ->label('E-Mail-Adresse')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('owner_password')
                            ->label('Initialpasswort')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->helperText('Der Inhaber sollte das Passwort nach dem ersten Login ändern.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
