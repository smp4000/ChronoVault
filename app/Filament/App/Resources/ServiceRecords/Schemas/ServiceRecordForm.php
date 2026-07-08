<?php

/**
 * =========================================================================
 * ServiceRecordForm — Formular-Schema der Servicevorgänge
 * =========================================================================
 *
 * Zweck:
 *   Wird von der ServiceRecordResource UND dem
 *   ServiceRecordsRelationManager genutzt (withWatch: false = Uhr steht
 *   fest). Der STATUS ist bewusst kein Formularfeld — der Workflow läuft
 *   über die Actions (Start beim Anlegen, "Abschließen"-Aktion).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceRecords\Schemas;

use App\Enums\ServiceType;
use App\Models\Contact;
use App\Models\Watch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceRecordForm
{
    public static function configure(Schema $schema, bool $withWatch = true): Schema
    {
        return $schema
            ->components([
                Section::make('Servicevorgang')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->columns(2)
                    ->components(array_filter([
                        $withWatch
                            ? Select::make('watch_id')
                                ->label('Uhr')
                                ->options(fn (): array => Watch::query()
                                    ->with('brand')
                                    ->get()
                                    ->mapWithKeys(fn (Watch $watch): array => [$watch->id => $watch->fullName()])
                                    ->all())
                                ->searchable()
                                ->required()
                                ->disabledOn('edit')
                            : null,

                        Select::make('type')
                            ->label('Art')
                            ->options(ServiceType::class)
                            ->default(ServiceType::FullService)
                            ->required(),

                        self::workshopSelect(),

                        DatePicker::make('submitted_at')
                            ->label('Eingereicht am')
                            ->default(now())
                            ->maxDate(now()),

                        TextInput::make('cost')
                            ->label('Kosten')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->helperText('Kann beim Abschluss aktualisiert werden.'),

                        TextInput::make('document_number')
                            ->label('Auftrags-/Belegnummer')
                            ->maxLength(255),

                        DatePicker::make('completed_at')
                            ->label('Abgeschlossen am')
                            ->visibleOn('edit')
                            ->disabled()
                            ->helperText('Wird über die Aktion „Abschließen" gesetzt.'),

                        DatePicker::make('warranty_until')
                            ->label('Service-Garantie bis')
                            ->visibleOn('edit'),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Was ist zu tun bzw. wurde gemacht?'),

                        Textarea::make('notes')
                            ->label('Interne Notizen')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])),
            ]);
    }

    /**
     * Werkstatt-Auswahl (Kontakte) — auch von der "In Service geben"-
     * Schnellaktion der Bestandsliste genutzt.
     */
    public static function workshopSelect(): Select
    {
        return Select::make('contact_id')
            ->label('Werkstatt/Dienstleister')
            ->options(fn (): array => Contact::query()
                ->orderBy('company_name')
                ->orderBy('last_name')
                ->get()
                ->mapWithKeys(fn (Contact $contact): array => [$contact->id => $contact->displayName()])
                ->all())
            ->searchable()
            ->helperText('Optional — leer bei hausinternem Service.');
    }
}
