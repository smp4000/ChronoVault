<?php

/**
 * =========================================================================
 * WatchForm — Formular-Schema des Uhrenbestands (Tenant-Panel)
 * =========================================================================
 *
 * Zweck:
 *   Anlage & Bearbeitung von Uhren in einem Tab-Layout nach
 *   Chrono24-Vorbild — standardisierte Auswahlfelder (Enums) statt
 *   Freitext, damit Filter, Auswertungen und der spätere Inserat-Export
 *   sauber funktionieren:
 *   Uhr → Zustand & Status → Gehäuse → Zifferblatt & Band → Notizen.
 *
 * KI-Referenz-Lookup:
 *   Die Referenznummer steht bewusst an ERSTER Stelle. Ihre Suffix-Action
 *   ruft den WatchReferenceLookupService (Anthropic Claude + Web-Suche):
 *   Felder werden befüllt (Enum-Codes), Marke/Kaliber gegen die
 *   Stammdaten aufgelöst (NIE automatisch angelegt), Bild-/Quellen-URLs
 *   landen im Hidden-Feld research_data (Media Library folgt in Modul 4).
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
use App\Enums\BraceletMaterial;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\WatchColor;
use App\Enums\WatchCondition;
use App\Enums\WatchGender;
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
                                    ->placeholder('z. B. 126610LN oder CBZ208B.BF0009')
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

                                TextInput::make('model_name')
                                    ->label('Modell')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('z. B. Formula 1 Chronograph x Gulf'),

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

                                Select::make('movement_type')
                                    ->label('Aufzug')
                                    ->options(MovementType::class)
                                    ->helperText('Falls kein Kaliber erfasst ist.'),

                                Select::make('gender')
                                    ->label('Geschlecht')
                                    ->options(WatchGender::class),

                                TextInput::make('production_year')
                                    ->label('Herstellungsjahr')
                                    ->numeric()
                                    ->minValue(1700)
                                    ->maxValue(now()->year),

                                Toggle::make('is_production_year_approximate')
                                    ->label('Ungefähre Angabe')
                                    ->inline(false)
                                    ->helperText('Jahr ist geschätzt (z. B. aus der Seriennummer abgeleitet).'),

                                TextInput::make('serial_number')
                                    ->label('Seriennummer')
                                    ->maxLength(255)
                                    ->helperText('Wird nie veröffentlicht — nur interne Dokumentation.'),

                                TextInput::make('stock_number')
                                    ->label('Bestandsnummer')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Interne Nummer Ihres Betriebs — muss eindeutig sein.'),

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

                        Tab::make('Gehäuse')
                            ->icon('heroicon-m-squares-2x2')
                            ->columns(2)
                            ->components([
                                Select::make('case_material')
                                    ->label('Material Gehäuse')
                                    ->options(CaseMaterial::class)
                                    ->searchable(),

                                Select::make('glass_type')
                                    ->label('Glas')
                                    ->options(GlassType::class),

                                TextInput::make('case_diameter_mm')
                                    ->label('Durchmesser')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.1)
                                    ->suffix('mm'),

                                TextInput::make('case_height_mm')
                                    ->label('Durchmesser (2. Dimension)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.1)
                                    ->suffix('mm')
                                    ->helperText('Nur bei nicht-runden Gehäusen (Breite × Höhe).'),

                                Select::make('bezel_material')
                                    ->label('Material Lünette')
                                    ->options(CaseMaterial::class)
                                    ->searchable(),

                                Select::make('bezel_color')
                                    ->label('Farbe der Lünette')
                                    ->options(WatchColor::class)
                                    ->searchable(),

                                TextInput::make('water_resistance_bar')
                                    ->label('Wasserdichtigkeit')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(200)
                                    ->suffix('bar'),
                            ]),

                        Tab::make('Zifferblatt & Band')
                            ->icon('heroicon-m-swatch')
                            ->columns(2)
                            ->components([
                                Select::make('dial_color')
                                    ->label('Farbe Zifferblatt')
                                    ->options(WatchColor::class)
                                    ->searchable(),

                                Select::make('dial_numerals')
                                    ->label('Zifferblatt-Zahlen')
                                    ->options(DialNumerals::class),

                                Select::make('bracelet_material')
                                    ->label('Material Armband')
                                    ->options(BraceletMaterial::class)
                                    ->searchable(),

                                Select::make('bracelet_color')
                                    ->label('Farbe Armband')
                                    ->options(WatchColor::class)
                                    ->searchable(),

                                Select::make('clasp_type')
                                    ->label('Schließe')
                                    ->options(ClaspType::class),

                                Select::make('clasp_material')
                                    ->label('Material Schließe')
                                    ->options(CaseMaterial::class)
                                    ->searchable(),

                                TextInput::make('lug_width_mm')
                                    ->label('Bandanstoßbreite')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(50)
                                    ->suffix('mm'),
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
     * eindeutigem Stammdaten-Treffer; Enum-Felder als Codes.
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
            'movement_type' => ['Aufzug', $data->movementType?->value],
            'production_year' => ['Herstellungsjahr', $data->productionYearFrom],
            'gender' => ['Geschlecht', $data->gender?->value],
            'case_material' => ['Gehäusematerial', $data->caseMaterial?->value],
            'case_diameter_mm' => ['Durchmesser', $data->caseDiameterMm],
            'case_height_mm' => ['2. Dimension', $data->caseHeightMm],
            'glass_type' => ['Glas', $data->glassType?->value],
            'bezel_material' => ['Lünettenmaterial', $data->bezelMaterial?->value],
            'bezel_color' => ['Lünettenfarbe', $data->bezelColor?->value],
            'water_resistance_bar' => ['Wasserdichtigkeit', $data->waterResistanceBar],
            'dial_color' => ['Zifferblattfarbe', $data->dialColor?->value],
            'dial_numerals' => ['Zifferblatt-Zahlen', $data->dialNumerals?->value],
            'bracelet_material' => ['Armbandmaterial', $data->braceletMaterial?->value],
            'bracelet_color' => ['Armbandfarbe', $data->braceletColor?->value],
            'clasp_type' => ['Schließe', $data->claspType?->value],
            'clasp_material' => ['Schließenmaterial', $data->claspMaterial?->value],
            'lug_width_mm' => ['Bandanstoß', $data->lugWidthMm],
        ];

        foreach ($fieldMap as $field => [$label, $value]) {
            if ($value !== null) {
                $set($field, $value);
                $filled[] = $label;
            }
        }

        // KI-Jahresangaben sind Produktionszeiträume — als "ungefähr" markieren.
        if ($data->productionYearFrom !== null) {
            $set('is_production_year_approximate', true);
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
