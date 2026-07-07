<?php

/**
 * =========================================================================
 * WatchForm — Formular-Schema des Uhrenbestands (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Anlage & Bearbeitung von Uhren in einem Tab-Layout:
 *   Uhr → Zustand & Status → Gehäuse & Ausstattung → Notizen.
 *
 * KI-Referenz-Lookup:
 *   Die Referenznummer steht bewusst an ERSTER Stelle. Ihre Suffix-Action
 *   ruft den WatchReferenceLookupService (Anthropic Claude + Web-Suche):
 *   Felder werden befüllt, Marke/Kaliber gegen die Stammdaten aufgelöst
 *   (NIE automatisch angelegt), Bild-/Quellen-URLs landen im Hidden-Feld
 *   research_data (Bild-Übernahme in die Media Library folgt in Modul 4).
 *   Die eigentliche Logik liegt im Service — hier nur UI-Verdrahtung.
 *
 * Abhängiges Kaliber-Feld:
 *   Die Kaliber-Auswahl zeigt nur Werke der gewählten Marke — die
 *   Marken-Auswahl ist deshalb live() und setzt das Kaliber beim
 *   manuellen Markenwechsel zurück.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Watches\Schemas;

use App\DataTransferObjects\WatchReferenceData;
use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Watch;
use App\Services\WatchReferenceLookupService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Throwable;

class WatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Uhr')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Uhr')
                            ->icon('heroicon-m-clock')
                            ->columns(2)
                            ->components([
                                TextInput::make('reference_number')
                                    ->label('Referenznummer')
                                    ->maxLength(255)
                                    ->placeholder('z. B. 126610LN')
                                    ->autofocus()
                                    ->suffixAction(self::aiLookupAction())
                                    ->helperText('Referenz eingeben und ✨ klicken — die KI recherchiert Daten und Bildquellen.'),

                                Select::make('brand_id')
                                    ->label('Marke')
                                    ->relationship(
                                        name: 'brand',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query, ?Watch $record): Builder => $query
                                            ->where('is_active', true)
                                            ->when(
                                                $record?->brand_id,
                                                fn (Builder $q, string $brandId): Builder => $q->orWhere('id', $brandId),
                                            ),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    // Manueller Markenwechsel: fremdes Kaliber zurücksetzen.
                                    ->afterStateUpdated(fn (Set $set) => $set('caliber_id', null)),

                                Select::make('caliber_id')
                                    ->label('Kaliber')
                                    ->relationship(
                                        name: 'caliber',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query, Get $get, ?Watch $record): Builder => $query
                                            ->where('brand_id', $get('brand_id'))
                                            ->where(fn (Builder $q): Builder => $q
                                                ->where('is_active', true)
                                                ->when(
                                                    $record?->caliber_id,
                                                    fn (Builder $qq, string $caliberId): Builder => $qq->orWhere('id', $caliberId),
                                                )),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Get $get): bool => blank($get('brand_id')))
                                    ->helperText('Optional — Auswahl erscheint nach Wahl der Marke.'),

                                TextInput::make('model_name')
                                    ->label('Modell')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('z. B. Submariner Date'),

                                TextInput::make('serial_number')
                                    ->label('Seriennummer')
                                    ->maxLength(255),

                                TextInput::make('stock_number')
                                    ->label('Bestandsnummer')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Interne Nummer Ihres Betriebs — muss eindeutig sein.'),

                                TextInput::make('production_year')
                                    ->label('Baujahr')
                                    ->numeric()
                                    ->minValue(1700)
                                    ->maxValue(now()->year),

                                // KI-Rechercheergebnis (Beschreibung, Bild-/Quellen-URLs);
                                // wird von der Lookup-Action gesetzt und als JSON persistiert.
                                Hidden::make('research_data'),
                            ]),

                        Tab::make('Zustand & Status')
                            ->icon('heroicon-m-shield-check')
                            ->columns(2)
                            ->components([
                                Select::make('condition')
                                    ->label('Zustand')
                                    ->options(WatchCondition::class)
                                    ->required(),

                                Select::make('status')
                                    ->label('Bestandsstatus')
                                    ->options(WatchStatus::class)
                                    ->default(WatchStatus::InStock)
                                    ->required(),

                                Toggle::make('has_box')
                                    ->label('Box vorhanden'),

                                Toggle::make('has_papers')
                                    ->label('Papiere vorhanden'),
                            ]),

                        Tab::make('Gehäuse & Ausstattung')
                            ->icon('heroicon-m-squares-2x2')
                            ->columns(2)
                            ->components([
                                TextInput::make('case_material')
                                    ->label('Gehäusematerial')
                                    ->maxLength(255)
                                    ->placeholder('z. B. Edelstahl, Gelbgold 18k'),

                                TextInput::make('case_diameter_mm')
                                    ->label('Gehäusedurchmesser')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.1)
                                    ->suffix('mm'),

                                TextInput::make('dial_color')
                                    ->label('Zifferblatt')
                                    ->maxLength(255)
                                    ->placeholder('z. B. Schwarz, Sunburst-Blau'),

                                TextInput::make('bracelet_material')
                                    ->label('Band')
                                    ->maxLength(255)
                                    ->placeholder('z. B. Oyster-Band Edelstahl, Leder'),
                            ]),

                        Tab::make('Notizen')
                            ->icon('heroicon-m-pencil-square')
                            ->components([
                                Textarea::make('notes')
                                    ->label('Interne Notizen')
                                    ->rows(6)
                                    ->helperText('Nur für Ihr Team sichtbar. Der KI-Lookup ergänzt hier die Kurzbeschreibung.'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Suffix-Action am Referenznummern-Feld: KI-Recherche starten,
     * Formularfelder befüllen, Ergebnis-Notification anzeigen.
     */
    private static function aiLookupAction(): Action
    {
        return Action::make('aiLookup')
            ->label('Mit KI ausfüllen')
            ->icon('heroicon-m-sparkles')
            ->tooltip('Daten & Bildquellen zur Referenznummer recherchieren')
            ->action(function (?string $state, Set $set, Get $get): void {
                if (blank($state)) {
                    Notification::make()
                        ->warning()
                        ->title('Referenznummer fehlt')
                        ->body('Bitte zuerst eine Referenznummer eingeben.')
                        ->send();

                    return;
                }

                $service = app(WatchReferenceLookupService::class);

                try {
                    // Bereits gewählte Marke als Recherche-Hinweis mitgeben.
                    $brandHint = filled($get('brand_id'))
                        ? Brand::query()->find($get('brand_id'))?->name
                        : null;

                    $data = $service->lookup(trim((string) $state), $brandHint);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('KI-Lookup fehlgeschlagen')->body($e->getMessage())->send();

                    return;
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()
                        ->danger()
                        ->title('KI-Lookup fehlgeschlagen')
                        ->body('Unerwarteter Fehler bei der Anfrage. Bitte später erneut versuchen.')
                        ->send();

                    return;
                }

                self::applyLookupResult($data, $service, $set, $get);
            });
    }

    /**
     * Überträgt das Rechercheergebnis in die Formularfelder.
     * Es werden nur belegte Werte gesetzt; Marke/Kaliber nur bei
     * eindeutigem Stammdaten-Treffer.
     */
    private static function applyLookupResult(
        WatchReferenceData $data,
        WatchReferenceLookupService $service,
        Set $set,
        Get $get,
    ): void {
        $filled = [];
        $warnings = [];

        $brand = $service->resolveBrand($data->brandName);

        if ($brand !== null) {
            $set('brand_id', $brand->id);
            $filled[] = 'Marke';
        } elseif ($data->brandName !== null) {
            $warnings[] = "Marke „{$data->brandName}“ ist nicht in Ihren Stammdaten — bitte manuell wählen.";
        }

        $caliber = $service->resolveCaliber($brand, $data->caliberName);

        if ($caliber !== null) {
            $set('caliber_id', $caliber->id);
            $filled[] = 'Kaliber';
        }

        $fieldMap = [
            'model_name' => ['Modell', $data->modelName],
            'production_year' => ['Baujahr', $data->productionYearFrom],
            'case_material' => ['Gehäusematerial', $data->caseMaterial],
            'case_diameter_mm' => ['Durchmesser', $data->caseDiameterMm],
            'dial_color' => ['Zifferblatt', $data->dialColor],
            'bracelet_material' => ['Band', $data->braceletMaterial],
        ];

        foreach ($fieldMap as $field => [$label, $value]) {
            if ($value !== null) {
                $set($field, $value);
                $filled[] = $label;
            }
        }

        // Kurzbeschreibung in die Notizen — vorhandene Notizen nie überschreiben.
        if ($data->description !== null && blank($get('notes'))) {
            $set('notes', $data->description);
            $filled[] = 'Notizen';
        }

        $set('research_data', $data->toResearchData());

        $imageCount = count($data->imageUrls);
        $body = $filled === []
            ? 'Die KI konnte keine Daten sicher belegen.'
            : 'Befüllt: '.implode(', ', $filled).'.';

        if ($imageCount > 0) {
            $body .= " {$imageCount} Bildquelle(n) gespeichert — Übernahme in die Medienverwaltung folgt mit Modul 4.";
        }

        if ($warnings !== []) {
            $body .= ' '.implode(' ', $warnings);
        }

        Notification::make()
            ->{$filled === [] ? 'warning' : 'success'}()
            ->title($filled === [] ? 'Recherche ohne verwertbares Ergebnis' : 'Recherche abgeschlossen')
            ->body($body)
            ->send();
    }
}
