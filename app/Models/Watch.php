<?php

/**
 * =========================================================================
 * Watch — Uhr (Kernentität, Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Repräsentiert eine physische Uhr im Bestand eines Betriebs — DAS
 *   Kernobjekt der Plattform. Referenziert die Stammdaten aus Modul 2
 *   (Brand Pflicht, Caliber optional).
 *
 * Verantwortlichkeiten:
 *   - Beziehungen zu Brand/Caliber
 *   - Volltextsuche via Laravel Scout (database-Driver lokal, ADR-003 —
 *     der Umstieg auf Meilisearch ist nur ein Driver-Wechsel)
 *   - Anzeige-Helfer fullName() für Notifications/Global Search
 *
 * WARUM keine Preisfelder:
 *   Einkauf/Verkauf/Preishistorie werden eigene Tabellen (Modul 5) —
 *   eine Uhr kann mehrfach den Besitzer wechseln (An- und Wiederverkauf).
 *
 * Mögliche Erweiterungen:
 *   - Fotos/Zertifikate (Modul 4, spatie/laravel-medialibrary)
 *   - Kauf-/Verkaufsbeziehungen (Modul 5), Servicehistorie (Modul 6)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BezelType;
use App\Enums\BraceletMaterial;
use App\Enums\CaseBack;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\OwnershipStatus;
use App\Enums\WatchColor;
use App\Enums\WatchCondition;
use App\Enums\WatchGender;
use App\Enums\WatchStatus;
use App\Observers\WatchObserver;
use Database\Factories\WatchFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy([WatchObserver::class])]
class Watch extends Model implements HasMedia
{
    /** @use HasFactory<WatchFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;
    use Searchable;
    use SoftDeletes;

    /**
     * Explizit, weil Laravel sonst "watches" korrekt rät — aber die
     * Kernentität der Plattform verdient keine Implizitheit.
     */
    protected $table = 'watches';

    /**
     * Model-seitige Defaults — der DB-Default greift nur in der Datenbank,
     * nicht am frisch erstellten Model-Objekt.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ownership_status' => 'owned',
    ];

    protected $fillable = [
        'created_by_user_id',
        'brand_id',
        'caliber_id',
        'movement_type',
        'model_name',
        'reference_number',
        'serial_number',
        'stock_number',
        'production_year',
        'is_production_year_approximate',
        'gender',
        'condition',
        'status',
        'is_published',
        'allow_direct_buy',
        'asking_price',
        'previous_asking_price',
        'price_reduced_at',
        'has_box',
        'has_papers',
        'case_material',
        'case_diameter_mm',
        'case_height_mm',
        'glass_type',
        'bezel_material',
        'bezel_color',
        'bezel_type',
        'case_back',
        'water_resistance_bar',
        'dial_color',
        'dial_numerals',
        'dial_finish',
        'bracelet_material',
        'bracelet_color',
        'clasp_type',
        'clasp_material',
        'lug_width_mm',
        'lug_to_lug_mm',
        'functions',
        'ownership_status',
        'owner_name',
        'owner_address',
        'storage_location',
        'purchase_price',
        'purchase_date',
        'purchase_location',
        'delivery_scope',
        'is_limited_edition',
        'limited_edition_number',
        'limited_edition_total',
        'description',
        'notes',
        'research_data',
        'insurance_company',
        'insurance_policy_number',
        'insurance_value',
        'insurance_valid_until',
        'insurance_notes',
        'photo_slots',
        'photos',
        'watchcharts_uuid',
        'current_market_value',
        'last_valuation_at',
        'wishlist_target_price',
        'wishlist_notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_year' => 'integer',
            'is_production_year_approximate' => 'boolean',
            'condition' => WatchCondition::class,
            'status' => WatchStatus::class,
            'is_published' => 'boolean',
            'allow_direct_buy' => 'boolean',
            'asking_price' => 'decimal:2',
            'previous_asking_price' => 'decimal:2',
            'price_reduced_at' => 'datetime',
            'movement_type' => MovementType::class,
            'gender' => WatchGender::class,
            'has_box' => 'boolean',
            'has_papers' => 'boolean',
            'case_material' => CaseMaterial::class,
            'case_diameter_mm' => 'decimal:1',
            'case_height_mm' => 'decimal:1',
            'glass_type' => GlassType::class,
            'bezel_material' => CaseMaterial::class,
            'bezel_color' => WatchColor::class,
            'bezel_type' => BezelType::class,
            'case_back' => CaseBack::class,
            'water_resistance_bar' => 'integer',
            'dial_color' => WatchColor::class,
            'dial_numerals' => DialNumerals::class,
            'bracelet_material' => BraceletMaterial::class,
            'bracelet_color' => WatchColor::class,
            'clasp_type' => ClaspType::class,
            'clasp_material' => CaseMaterial::class,
            'lug_width_mm' => 'integer',
            'lug_to_lug_mm' => 'decimal:2',
            // Funktionen als Array von WatchFunction-Codes (Mehrfachauswahl)
            'functions' => 'array',
            'ownership_status' => OwnershipStatus::class,
            'purchase_price' => 'decimal:2',
            'purchase_date' => 'date',
            'is_limited_edition' => 'boolean',
            'limited_edition_number' => 'integer',
            'limited_edition_total' => 'integer',
            'insurance_value' => 'decimal:2',
            'insurance_valid_until' => 'date',
            // Modul 7 (Bewertungen) pflegt diese Felder
            'current_market_value' => 'decimal:2',
            'last_valuation_at' => 'datetime',
            // Wunschliste (Status wishlist): Zielpreis + Alarm-Sperre
            'wishlist_target_price' => 'decimal:2',
            'wishlist_notified_at' => 'datetime',
            // Modul 4 (geführter Foto-Upload) nutzt diese Slots
            'photo_slots' => 'array',
            // Gespeicherte Fotos (Pfade auf der tenant-isolierten public-Disk)
            'photos' => 'array',
            // KI-Rechercheergebnis (Beschreibung, Bild-/Quellen-URLs) —
            // Bild-Übernahme in die Media Library folgt in Modul 4.
            'research_data' => 'array',
        ];
    }

    /**
     * Erfasser automatisch setzen (Tenant-Benutzer) — analog zum
     * user_id-Feld der Vorgänger-Anwendung, aber nur dokumentierend.
     */
    protected static function booted(): void
    {
        static::creating(function (Watch $watch): void {
            $watch->created_by_user_id ??= auth()->id();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Caliber, $this>
     */
    public function caliber(): BelongsTo
    {
        return $this->belongsTo(Caliber::class);
    }

    /**
     * Kauf-/Verkaufshistorie der Uhr (Modul 5), neueste zuerst.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderByDesc('transacted_at');
    }

    /**
     * Service-Historie der Uhr (Modul 6), neueste zuerst.
     *
     * @return HasMany<ServiceRecord, $this>
     */
    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class)->orderByDesc('created_at');
    }

    /**
     * Bewertungs-Historie der Uhr (Modul 7), neueste zuerst.
     *
     * @return HasMany<Valuation, $this>
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(Valuation::class)->orderByDesc('valued_at');
    }

    /**
     * Auktions-Historie der Uhr (Modul 8), neueste zuerst.
     *
     * @return HasMany<AuctionLot, $this>
     */
    public function auctionLots(): HasMany
    {
        return $this->hasMany(AuctionLot::class)->orderByDesc('created_at');
    }

    /**
     * Anzeige-Name "Marke Modell (Referenz)" — z. B. für Global Search,
     * Notifications und spätere Belege.
     */
    public function fullName(): string
    {
        // brand_id ist Pflichtfeld — die Beziehung existiert immer.
        $name = trim($this->brand->name.' '.$this->model_name);

        return $this->reference_number !== null
            ? $name.' ('.$this->reference_number.')'
            : $name;
    }

    /**
     * Medien-Collections (Modul 4):
     * - photos    : Uhrenfotos (nur Bilder; KI-Download + manueller Upload)
     * - documents : Zertifikate, Kaufbelege, Servicehefte (PDF + Bilder)
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Öffentliche URLs der Fotos (Media Library, tenant-isolierte
     * Auslieferung via TenantMediaUrlGenerator). Fallback auf die
     * Alt-Spalte photos (JSON-Pfade), bis watches:migrate-photos lief.
     *
     * @return array<int, string>
     */
    public function photoUrls(): array
    {
        // Die Sortier-Reihenfolge (media.order_column) bestimmt Galerie
        // und Shop-Kachel — im Panel per Drag & Drop änderbar
        // (WatchPhotoGallery); das erste Bild ist das Hauptbild.
        // ?v=<updated_at>: Cache-Buster — nach Bearbeitungen (z. B.
        // Wasserzeichen) ändert sich die URL, sonst liefern Cloudflare/
        // Browser das alte gecachte Bild aus.
        $mediaUrls = $this->getMedia('photos')
            ->map(fn ($media): string => $media->getUrl().'?v='.($media->updated_at?->getTimestamp() ?? 0))
            ->all();

        if ($mediaUrls !== []) {
            return $mediaUrls;
        }

        return array_map(
            fn (string $path): string => tenant_asset($path),
            $this->photos ?? [],
        );
    }

    /**
     * URL des ersten Fotos — für Listen-Thumbnails.
     */
    public function firstPhotoUrl(): ?string
    {
        return $this->photoUrls()[0] ?? null;
    }

    /**
     * Erstes Foto als Binärdaten für E-Mails (Inline-Einbettung via
     * $message->embedData). Extern verlinkte Bilder blockieren viele
     * Mailprogramme — eingebettet wird das Foto immer angezeigt.
     * WebP/AVIF (typisch für Hersteller-CDNs) werden nach JPEG
     * konvertiert — Outlook zeigt diese Formate sonst nicht an.
     * null, wenn kein Foto vorhanden oder die Datei fehlt.
     *
     * @return array{data: string, mime: string, name: string}|null
     */
    public function firstPhotoForEmail(): ?array
    {
        $media = $this->getFirstMedia('photos');

        if ($media !== null) {
            $path = $media->getPath();

            if (! is_file($path)) {
                return null;
            }

            return $this->mailSafePhoto(
                (string) file_get_contents($path),
                (string) $media->mime_type,
                (string) $media->file_name,
            );
        }

        // Fallback auf die Alt-Spalte photos (JSON-Pfade auf der public-Disk)
        $legacy = $this->getAttribute('photos');
        $firstPath = is_array($legacy) ? ($legacy[0] ?? null) : null;

        if (! is_string($firstPath) || ! Storage::disk('public')->exists($firstPath)) {
            return null;
        }

        return $this->mailSafePhoto(
            (string) Storage::disk('public')->get($firstPath),
            (string) (Storage::disk('public')->mimeType($firstPath) ?: 'image/jpeg'),
            basename($firstPath),
        );
    }

    /**
     * Mail-taugliches Format erzwingen: WebP/AVIF via GD nach JPEG
     * konvertieren. Schlägt die Konvertierung fehl, wird das Original
     * geliefert (besser als gar kein Bild).
     *
     * @return array{data: string, mime: string, name: string}
     */
    private function mailSafePhoto(string $data, string $mime, string $name): array
    {
        $original = ['data' => $data, 'mime' => $mime, 'name' => $name];

        if (! in_array($mime, ['image/webp', 'image/avif'], true)) {
            return $original;
        }

        try {
            $image = @imagecreatefromstring($data);

            if ($image === false) {
                return $original;
            }

            ob_start();
            imagejpeg($image, null, 85);
            $jpeg = (string) ob_get_clean();
            imagedestroy($image);

            if ($jpeg === '') {
                return $original;
            }

            return [
                'data' => $jpeg,
                'mime' => 'image/jpeg',
                'name' => pathinfo($name, PATHINFO_FILENAME).'.jpg',
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return $original;
        }
    }

    /**
     * Ist die Uhr verkauft? (getAttribute statt Property-Zugriff —
     * kapselt den Enum-Vergleich typsicher für statische Analyse.)
     */
    public function isSold(): bool
    {
        return $this->getAttribute('status') === WatchStatus::Sold;
    }

    /**
     * Ist die Uhr aktuell im Service?
     */
    public function isInService(): bool
    {
        return $this->getAttribute('status') === WatchStatus::InService;
    }

    /**
     * Formatierter Shop-Verkaufspreis („12.500 €") — null, wenn kein
     * Preis hinterlegt ist (der Shop zeigt dann „Preis auf Anfrage").
     * Ganze Beträge ohne Nachkommastellen, krumme mit zwei.
     */
    /**
     * Rabatt in Prozent bei gesenktem Preis — null ohne Preissenkung.
     * Nur für kaufbare Uhren sinnvoll (Streichpreis-Anzeige im Shop).
     */
    public function discountPercent(): ?int
    {
        $current = $this->getAttribute('asking_price');
        $previous = $this->getAttribute('previous_asking_price');

        if ($current === null || $previous === null || (float) $previous <= (float) $current) {
            return null;
        }

        $percent = (int) round((1 - (float) $current / (float) $previous) * 100);

        return $percent > 0 ? $percent : null;
    }

    /**
     * Streichpreis formatiert — null ohne aktive Preissenkung.
     */
    public function formattedPreviousPrice(): ?string
    {
        if ($this->discountPercent() === null) {
            return null;
        }

        $value = (float) $this->getAttribute('previous_asking_price');
        $decimals = fmod($value, 1.0) > 0.0 ? 2 : 0;

        return number_format($value, $decimals, ',', '.').' €';
    }

    public function formattedAskingPrice(): ?string
    {
        $price = $this->getAttribute('asking_price');

        if ($price === null) {
            return null;
        }

        $value = (float) $price;
        $decimals = fmod($value, 1.0) > 0.0 ? 2 : 0;

        return number_format($value, $decimals, ',', '.').' €';
    }

    /**
     * Scope: im öffentlichen Shop KAUFBARE Uhren — veröffentlicht UND
     * verkäuflich (An Lager/Kommission). Basis für Sofortkauf & Anfragen.
     *
     * @param  Builder<Watch>  $query
     * @return Builder<Watch>
     */
    public function scopePublishedInShop(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereIn('status', array_column(WatchStatus::shopSellableStatuses(), 'value'));
    }

    /**
     * Scope: im Shop SICHTBARE Uhren — zusätzlich zu den verkäuflichen
     * auch Reserviert, In Auktion und Verkauft. Die bleiben bewusst als
     * Referenz mit Badge im Schaufenster (sonst wirkt der Shop leer),
     * sind aber nicht kaufbar. Nur „Im Service" bleibt intern.
     *
     * @param  Builder<Watch>  $query
     * @return Builder<Watch>
     */
    public function scopeVisibleInShop(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereIn('status', [
                WatchStatus::InStock->value,
                WatchStatus::Consignment->value,
                // Eigentum: nur wenn bewusst veröffentlicht (Statuswechsel
                // entfernt die Veröffentlichung — siehe WatchObserver)
                WatchStatus::PrivateCollection->value,
                WatchStatus::Reserved->value,
                WatchStatus::InAuction->value,
                WatchStatus::Sold->value,
            ]);
    }

    /**
     * Zielpreis der Wunschliste erreicht? (Marktwert auf/unter Ziel —
     * nur für Uhren mit Status "Wunschliste" relevant)
     */
    public function wishlistTargetReached(): bool
    {
        return $this->getAttribute('status') === WatchStatus::Wishlist
            && $this->wishlist_target_price !== null
            && $this->current_market_value !== null
            && (float) $this->current_market_value <= (float) $this->wishlist_target_price;
    }

    /**
     * Ist die Uhr im Shop aktuell kaufbar (An Lager/Kommission)?
     */
    public function isBuyableInShop(): bool
    {
        return in_array($this->getAttribute('status'), WatchStatus::shopSellableStatuses(), true);
    }

    /**
     * Badge-Text fürs Schaufenster — null, wenn die Uhr normal
     * kaufbar ist (dann braucht die Kachel kein Badge).
     */
    public function shopStatusBadge(): ?string
    {
        return match ($this->getAttribute('status')) {
            WatchStatus::Sold => 'Verkauft',
            WatchStatus::Reserved => 'Reserviert',
            WatchStatus::InAuction => 'In Auktion',
            default => null,
        };
    }

    /**
     * Scout-Suchindex (database-Driver: LIKE-Suche über diese Felder).
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'model_name' => $this->model_name,
            'reference_number' => $this->reference_number,
            'serial_number' => $this->serial_number,
            'stock_number' => $this->stock_number,
        ];
    }
}
