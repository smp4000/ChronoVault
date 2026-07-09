<?php

/**
 * =========================================================================
 * BusinessSettings — Betriebsdaten des Mandanten (App-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Bankverbindung des Betriebs für die automatische Auktions-Abwicklung
 *   (Gewinner-Mail mit Zahlungsinformationen + GiroCode-QR, Modul 8b).
 *
 * Speicherort:
 *   Im data-JSON des zentralen Tenant-Models (stancl Custom Columns:
 *   alles außer id/name/slug/status landet in "data") — das Tenant-Model
 *   nutzt immer die zentrale Verbindung, Schreiben aus dem Tenant-Kontext
 *   ist daher sicher. Zugriff via tenant('bank_iban') etc.
 *
 * Zugriff: settings.manage (Inhaber + Admin).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BusinessSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $title = 'Betriebsdaten';

    protected static ?string $navigationLabel = 'Betriebsdaten';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.app.pages.business-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $this->getSchema('form')?->fill([
            'company_street' => tenant('company_street'),
            'company_postal_code' => tenant('company_postal_code'),
            'company_city' => tenant('company_city'),
            'tax_number' => tenant('tax_number'),
            'vat_id' => tenant('vat_id'),
            'tax_mode' => tenant('tax_mode') ?? 'differential',
            'bank_account_holder' => tenant('bank_account_holder'),
            'bank_iban' => tenant('bank_iban'),
            'bank_bic' => tenant('bank_bic'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Anschrift & Steuern')
                    ->description('Pflichtangaben für Rechnungen und Kaufverträge (§ 14 UStG).')
                    ->icon('heroicon-m-building-office-2')
                    ->columns(2)
                    ->components([
                        TextInput::make('company_street')
                            ->label('Straße und Hausnummer')
                            ->maxLength(255),

                        TextInput::make('company_postal_code')
                            ->label('PLZ')
                            ->maxLength(20),

                        TextInput::make('company_city')
                            ->label('Ort')
                            ->maxLength(255),

                        Select::make('tax_mode')
                            ->label('Besteuerung')
                            ->options([
                                'differential' => 'Differenzbesteuerung (§ 25a UStG) — üblich im Gebrauchtuhrenhandel',
                                'regular' => 'Regelbesteuerung (19 % USt.)',
                                'small_business' => 'Kleinunternehmer (§ 19 UStG)',
                            ])
                            ->default('differential')
                            ->required()
                            ->helperText('Bestimmt den Steuerausweis auf Rechnungen und in der E-Rechnung.'),

                        TextInput::make('tax_number')
                            ->label('Steuernummer')
                            ->maxLength(30)
                            ->helperText('Steuernummer ODER USt-IdNr. ist Pflicht auf Rechnungen.'),

                        TextInput::make('vat_id')
                            ->label('USt-IdNr.')
                            ->placeholder('DE123456789')
                            ->maxLength(20),
                    ]),

                Section::make('Bankverbindung')
                    ->description('Wird für die Zahlungsinformationen in der Zuschlag-Mail an Auktionsgewinner verwendet (inkl. Überweisungs-QR-Code).')
                    ->icon('heroicon-m-banknotes')
                    ->columns(2)
                    ->components([
                        TextInput::make('bank_account_holder')
                            ->label('Kontoinhaber')
                            ->placeholder('z. B. Welle Uhrenhandel GmbH')
                            ->maxLength(70)
                            ->columnSpanFull(),

                        TextInput::make('bank_iban')
                            ->label('IBAN')
                            ->placeholder('DE00 0000 0000 0000 0000 00')
                            ->maxLength(42),

                        TextInput::make('bank_bic')
                            ->label('BIC')
                            ->placeholder('z. B. PBNKDEFF')
                            ->maxLength(11),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->getSchema('form')?->getState() ?? [];

        // IBAN normalisieren (Leerzeichen raus, Großbuchstaben) — der
        // GiroCode verlangt das kompakte Format.
        $iban = strtoupper(str_replace(' ', '', (string) ($data['bank_iban'] ?? '')));

        tenant()->update([
            'company_street' => $data['company_street'] ?? null,
            'company_postal_code' => $data['company_postal_code'] ?? null,
            'company_city' => $data['company_city'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'vat_id' => strtoupper((string) ($data['vat_id'] ?? '')) ?: null,
            'tax_mode' => $data['tax_mode'] ?? 'differential',
            'bank_account_holder' => $data['bank_account_holder'] ?? null,
            'bank_iban' => $iban !== '' ? $iban : null,
            'bank_bic' => strtoupper((string) ($data['bank_bic'] ?? '')) ?: null,
        ]);

        Notification::make()
            ->success()
            ->title('Betriebsdaten gespeichert')
            ->send();
    }
}
