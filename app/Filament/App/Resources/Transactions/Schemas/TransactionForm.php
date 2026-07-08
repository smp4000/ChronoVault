<?php

/**
 * =========================================================================
 * TransactionForm — Formular-Schema der Kauf-/Verkaufsbelege
 * =========================================================================
 *
 * Zweck:
 *   Wird von der TransactionResource UND dem TransactionsRelationManager
 *   (WatchResource) genutzt — im Relation-Manager-Kontext steht die Uhr
 *   fest, `withWatch: false` blendet das Uhren-Feld aus.
 *
 * Guardrails:
 *   Uhr und Art sind nach der Erstellung nicht mehr änderbar — ein
 *   Typwechsel würde den synchronisierten Uhren-Status entkoppeln
 *   (Storno = Beleg löschen + neu erfassen).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Transactions\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Watch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema, bool $withWatch = true): Schema
    {
        return $schema
            ->components([
                Section::make('Beleg')
                    ->icon('heroicon-m-banknotes')
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
                            ->options(TransactionType::class)
                            ->default(TransactionType::Sale)
                            ->required()
                            ->disabledOn('edit'),

                        self::contactSelect(),

                        TextInput::make('price')
                            ->label('Preis')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix('€'),

                        DatePicker::make('transacted_at')
                            ->label('Datum')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),

                        Select::make('payment_method')
                            ->label('Zahlungsart')
                            ->options(PaymentMethod::class),

                        TextInput::make('document_number')
                            ->label('Belegnummer')
                            ->maxLength(255)
                            ->placeholder('z. B. VK-2026-0042'),

                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])),
            ]);
    }

    /**
     * Kontakt-Auswahl mit Anzeigename (Firma bzw. Vor-/Nachname) —
     * auch von der "Verkaufen"-Action der Bestandsliste genutzt.
     */
    public static function contactSelect(): Select
    {
        return Select::make('contact_id')
            ->label('Kontakt')
            ->options(fn (): array => Contact::query()
                ->orderBy('company_name')
                ->orderBy('last_name')
                ->get()
                ->mapWithKeys(fn (Contact $contact): array => [$contact->id => $contact->displayName()])
                ->all())
            ->searchable()
            ->helperText('Käufer bzw. Verkäufer — optional.');
    }
}
