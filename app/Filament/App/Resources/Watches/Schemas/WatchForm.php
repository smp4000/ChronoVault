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
use App\Enums\BezelType;
use App\Enums\BraceletMaterial;
use App\Enums\CaseBack;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\OwnershipStatus;
use App\Enums\PhotoSlot;
use App\Enums\WatchColor;
use App\Enums\WatchCondition;
use App\Enums\WatchFunction;
use App\Enums\WatchGender;
use App\Enums\WatchStatus;
use App\Livewire\WatchPhotoGallery;
use App\Models\Brand;
use App\Models\Caliber;
use App\Models\Watch;
use App\Services\WatchReferenceLookupService;
use App\Support\QrPng;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
                                    // "+"-Button: fehlendes Kaliber direkt anlegen,
                                    // ohne das Uhren-Formular zu verlassen.
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Kaliberbezeichnung')
                                            ->placeholder('z. B. Calibre 16')
                                            ->required()
                                            ->maxLength(255),

                                        Select::make('movement_type')
                                            ->label('Aufzug')
                                            ->options(MovementType::class)
                                            ->default(MovementType::Automatic)
                                            ->required(),

                                        TextInput::make('power_reserve_hours')
                                            ->label('Gangreserve')
                                            ->numeric()
                                            ->minValue(1)
                                            ->suffix('Std.'),

                                        TextInput::make('frequency_vph')
                                            ->label('Frequenz der Unruh')
                                            ->numeric()
                                            ->minValue(1)
                                            ->suffix('A/h')
                                            ->placeholder('z. B. 28800'),

                                        TextInput::make('jewels')
                                            ->label('Steine (Jewels)')
                                            ->numeric()
                                            ->minValue(0),
                                    ])
                                    ->createOptionUsing(fn (array $data, Get $get): string => Caliber::create([
                                        ...$data,
                                        'brand_id' => $get('brand_id'),
                                        'is_active' => true,
                                    ])->getKey())
                                    ->helperText('Optional — Auswahl erscheint nach Wahl der Marke. Fehlt das Kaliber: über das + direkt anlegen.'),

                                Select::make('movement_type')
                                    ->label('Aufzug')
                                    ->options(MovementType::class)
                                    ->helperText('Falls kein Kaliber erfasst ist.'),

                                Select::make('gender')
                                    ->label('Geschlecht')
                                    ->options(WatchGender::class),

                                Select::make('functions')
                                    ->label('Funktionen')
                                    ->options(WatchFunction::class)
                                    ->multiple()
                                    ->searchable()
                                    ->helperText('Komplikationen wie Chronograph, GMT, Mondphase …'),

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

                                Toggle::make('is_limited_edition')
                                    ->label('Limited Edition')
                                    ->inline(false)
                                    ->live(),

                                TextInput::make('limited_edition_number')
                                    ->label('Editionsnummer')
                                    ->numeric()
                                    ->minValue(1)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_limited_edition')),

                                TextInput::make('limited_edition_total')
                                    ->label('Auflage gesamt')
                                    ->numeric()
                                    ->minValue(1)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_limited_edition')),

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
                                    ->required()
                                    ->live(),

                                TextInput::make('wishlist_target_price')
                                    ->label('Zielpreis (Wunschliste)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->prefix('€')
                                    ->visible(fn (Get $get): bool => in_array(
                                        $get('status'),
                                        [WatchStatus::Wishlist, WatchStatus::Wishlist->value],
                                        true,
                                    ))
                                    ->helperText('Fällt der Marktwert der nächtlichen Bewertung auf oder unter diesen Wert, kommt eine Alarm-Mail.'),

                                Toggle::make('has_box')
                                    ->label('Box vorhanden'),

                                Toggle::make('has_papers')
                                    ->label('Papiere vorhanden'),

                                Select::make('ownership_status')
                                    ->label('Eigentumsverhältnis')
                                    ->options(OwnershipStatus::class)
                                    ->default(OwnershipStatus::Owned)
                                    ->required()
                                    ->live(),

                                TextInput::make('storage_location')
                                    ->label('Lagerort')
                                    ->maxLength(255)
                                    ->placeholder('z. B. Tresor 2, Fach 14'),

                                TextInput::make('owner_name')
                                    ->label('Eigentümer')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('ownership_status') !== OwnershipStatus::Owned->value
                                        && $get('ownership_status') !== OwnershipStatus::Owned),

                                Textarea::make('owner_address')
                                    ->label('Anschrift Eigentümer')
                                    ->rows(2)
                                    ->visible(fn (Get $get): bool => $get('ownership_status') !== OwnershipStatus::Owned->value
                                        && $get('ownership_status') !== OwnershipStatus::Owned),
                            ]),

                        Tab::make('Kauf & Versicherung')
                            ->icon('heroicon-m-banknotes')
                            ->columns(2)
                            ->components([
                                TextInput::make('purchase_price')
                                    ->label('Einkaufspreis')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('€')
                                    ->helperText('Verkäufe & Preishistorie folgen in Modul 5.'),

                                DatePicker::make('purchase_date')
                                    ->label('Kaufdatum')
                                    ->maxDate(now()),

                                TextInput::make('purchase_location')
                                    ->label('Gekauft bei')
                                    ->maxLength(255)
                                    ->placeholder('z. B. Privatankauf, Auktionshaus X'),

                                Textarea::make('delivery_scope')
                                    ->label('Lieferumfang')
                                    ->rows(2)
                                    ->placeholder('z. B. Umkarton, 3 Ersatzglieder, Kaufbeleg 2021')
                                    ->helperText('Zubehör über Box & Papiere hinaus.'),

                                TextInput::make('insurance_company')
                                    ->label('Versicherung')
                                    ->maxLength(255),

                                TextInput::make('insurance_policy_number')
                                    ->label('Policennummer')
                                    ->maxLength(255),

                                TextInput::make('insurance_value')
                                    ->label('Versicherungswert')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('€'),

                                DatePicker::make('insurance_valid_until')
                                    ->label('Versichert bis'),

                                Textarea::make('insurance_notes')
                                    ->label('Versicherungs-Notizen')
                                    ->rows(2)
                                    ->columnSpanFull(),
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

                                Select::make('bezel_type')
                                    ->label('Lünettentyp')
                                    ->options(BezelType::class),

                                Select::make('case_back')
                                    ->label('Gehäuseboden')
                                    ->options(CaseBack::class),

                                TextInput::make('water_resistance_bar')
                                    ->label('Wasserdichtigkeit')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(200)
                                    ->suffix('bar'),

                                TextInput::make('lug_to_lug_mm')
                                    ->label('Bandanstoß zu Bandanstoß')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.1)
                                    ->suffix('mm')
                                    ->helperText('Gehäuselänge über die Bandanstöße (Lug-to-Lug).'),
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

                                TextInput::make('dial_finish')
                                    ->label('Zifferblatt-Finish')
                                    ->maxLength(255)
                                    ->placeholder('z. B. Opalin & lackiert, Sonnenschliff'),

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

                        Tab::make('Fotos & Dokumente')
                            ->icon('heroicon-m-photo')
                            ->components([
                                Section::make('Geführter Foto-Upload')
                                    ->description('Die Standard-Perspektiven für ein vollständiges Inserat — ein Foto je Slot.')
                                    ->icon('heroicon-m-camera')
                                    ->columns(3)
                                    ->collapsible()
                                    ->components([
                                        self::mobileUploadQr(),
                                        ...self::photoSlotUploads(),
                                    ]),

                                Section::make('Weitere Fotos')
                                    ->icon('heroicon-m-photo')
                                    ->collapsible()
                                    ->components([
                                        SpatieMediaLibraryFileUpload::make('photos')
                                            ->hiddenLabel()
                                            ->collection('photos')
                                            // Nur Fotos OHNE Slot (Slots haben eigene Felder oben).
                                            ->filterMediaUsing(fn (Collection $media): Collection => $media
                                                ->filter(fn (Media $item): bool => blank($item->getCustomProperty('slot'))))
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([null, '1:1', '4:3', '16:9'])
                                            ->multiple()
                                            ->reorderable()
                                            ->maxFiles(20)
                                            ->maxSize(10240)
                                            ->panelLayout('grid')
                                            ->helperText('Zusätzliche Bilder — per Ziehen sortierbar (die Reihenfolge gilt auch im Shop). Stift-Symbol beim Upload: Zuschneiden, Drehen, Spiegeln.'),
                                    ]),

                                Section::make('Galerie-Reihenfolge')
                                    ->description('Alle Fotos der Uhr — per Ziehen sortieren, das erste Bild ist das Hauptbild im Shop.')
                                    ->icon('heroicon-m-arrows-up-down')
                                    ->collapsible()
                                    ->visible(fn (?Watch $record): bool => $record !== null)
                                    ->components([
                                        LivewireComponent::make(WatchPhotoGallery::class, fn (?Watch $record): array => ['watch' => $record])
                                            ->key('watch-photo-gallery'),
                                    ]),

                                Section::make('Zertifikate & Dokumente')
                                    ->icon('heroicon-m-document-text')
                                    ->collapsible()
                                    ->components([
                                        SpatieMediaLibraryFileUpload::make('documents')
                                            ->hiddenLabel()
                                            ->collection('documents')
                                            ->multiple()
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                            ->maxFiles(20)
                                            ->maxSize(20480)
                                            ->downloadable()
                                            ->openable()
                                            ->helperText('Zertifikate, Kaufbelege, Servicehefte (PDF oder Bild).'),
                                    ]),
                            ]),

                        Tab::make('Shop & Beschreibung')
                            ->icon('heroicon-m-building-storefront')
                            ->components([
                                Section::make('Öffentlicher Shop')
                                    ->description('Das Schaufenster Ihres Betriebs auf Ihrer Domain — nur veröffentlichte, verkäufliche Uhren erscheinen dort.')
                                    ->icon('heroicon-m-building-storefront')
                                    ->columns(2)
                                    ->components([
                                        Toggle::make('is_published')
                                            ->label('Im Shop veröffentlichen')
                                            ->inline(false)
                                            ->helperText('Verkaufte Uhren und Uhren im Service verschwinden automatisch. Beim Wechsel auf „Eigentum (Sammlung)" wird die Veröffentlichung entfernt — zum Anbieten einer Eigentums-Uhr hier wieder einschalten.'),

                                        Toggle::make('allow_direct_buy')
                                            ->label('Sofortkauf erlauben')
                                            ->inline(false)
                                            ->default(true)
                                            ->helperText('Aus = Interessenten können nur anfragen oder einen Preis vorschlagen. Privatverkäufer: Sofortkauf braucht eine hinterlegte Bankverbindung (Betriebsdaten).'),

                                        TextInput::make('asking_price')
                                            ->label('Verkaufspreis (Shop)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('€')
                                            ->helperText('Leer lassen für „Preis auf Anfrage".'),
                                    ]),

                                Textarea::make('description')
                                    ->label('Beschreibung')
                                    ->rows(5)
                                    ->helperText('Öffentlicher Text — erscheint auf der Shop-Detailseite. Der KI-Lookup ergänzt hier die Kurzbeschreibung.'),

                                Textarea::make('notes')
                                    ->label('Interne Notizen')
                                    ->rows(4)
                                    ->helperText('Nur für Ihr Team sichtbar.'),
                            ]),
                    ]),
            ]);
    }

    /**
     * QR-Code für die mobile Foto-Aufnahme: Scannen öffnet auf dem
     * Handy die Platzhalter-Seite (signierter Link, 24 h gültig) —
     * jedes Foto landet direkt an der Uhr. Nur beim Bearbeiten sichtbar
     * (eine neue Uhr hat noch keine ID für den Link).
     */
    private static function mobileUploadQr(): Placeholder
    {
        return Placeholder::make('mobile_upload_qr')
            ->hiddenLabel()
            ->columnSpanFull()
            ->visible(fn (?Watch $record): bool => $record !== null)
            ->content(function (?Watch $record): HtmlString {
                if ($record === null) {
                    return new HtmlString('');
                }

                $url = URL::temporarySignedRoute(
                    'watch.photos.mobile',
                    now()->addDay(),
                    ['watch' => $record->getKey()],
                );

                $qr = base64_encode(QrPng::make($url, 240));

                return new HtmlString(
                    '<div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">'
                    .'<img src="data:image/png;base64,'.$qr.'" alt="QR-Code für die mobile Foto-Aufnahme" '
                    .'style="width:9.5rem;height:9.5rem;border-radius:0.75rem;border:1px solid rgb(229 231 235);background:#fff;padding:0.25rem;">'
                    .'<div style="max-width:28rem;">'
                    .'<p style="font-weight:600;margin:0;">Mit dem Smartphone fotografieren</p>'
                    .'<p style="font-size:0.875rem;opacity:0.7;margin:0.375rem 0 0 0;line-height:1.55;">'
                    .'Scannen Sie den QR-Code — auf dem Handy öffnet sich die Foto-Seite mit allen '
                    .'Platzhaltern (Vorderseite, Rückseite, Armband …). Jedes Foto wird sofort an dieser '
                    .'Uhr gespeichert; danach hier die Seite neu laden. Der Link ist 24 Stunden gültig.</p>'
                    .'</div></div>'
                );
            });
    }

    /**
     * Ein Upload-Feld je Foto-Slot (geführter Upload). Alle Slots teilen
     * sich die photos-Collection; die Zuordnung läuft über die
     * custom_property "slot". deleteAbandonedFiles() der Komponente
     * respektiert den Media-Filter — die Felder löschen sich nicht
     * gegenseitig die Bilder weg.
     *
     * @return array<int, SpatieMediaLibraryFileUpload>
     */
    private static function photoSlotUploads(): array
    {
        return array_map(
            fn (PhotoSlot $slot): SpatieMediaLibraryFileUpload => SpatieMediaLibraryFileUpload::make('photo_slot_'.$slot->value)
                ->label($slot->getLabel())
                ->collection('photos')
                ->customProperties(['slot' => $slot->value])
                ->filterMediaUsing(fn (Collection $media): Collection => $media
                    ->filter(fn (Media $item): bool => $item->getCustomProperty('slot') === $slot->value))
                ->image()
                ->imageEditor()
                ->imageEditorAspectRatios([null, '1:1', '4:3'])
                ->maxSize(10240),
            PhotoSlot::cases(),
        );
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

        // Funktionen/Komplikationen (Mehrfachauswahl aus Enum-Codes).
        if ($data->functions !== []) {
            $set('functions', $data->functions);
            $filled[] = 'Funktionen';
        }

        // Kurzbeschreibung in die öffentliche Beschreibung —
        // vorhandene Texte nie überschreiben.
        if ($data->description !== null && blank($get('description'))) {
            $set('description', $data->description);
            $filled[] = 'Beschreibung';
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
