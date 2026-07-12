<?php

/**
 * =========================================================================
 * MarketplaceListing — Marktplatz-Eintrag (ZENTRALE Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Denormalisierter Spiegel einer veröffentlichten, kaufbaren Uhr aus
 *   einer Tenant-Datenbank für den zentralen Marktplatz (chrono-save.de).
 *   Eine Zeile je Uhr; Pflege ausschließlich über
 *   SyncWatchToMarketplaceAction (Observer + marketplace:sync).
 *
 * WARUM CentralConnection:
 *   Der Sync läuft im TENANT-Kontext (Observer beim Speichern einer
 *   Uhr). Der Trait pinnt dieses Model auf die zentrale Verbindung —
 *   Lese- und Schreibzugriffe landen immer in der zentralen DB,
 *   egal welcher Kontext gerade aktiv ist.
 *
 * Abhängigkeiten: stancl/tenancy (CentralConnection).
 * Erweiterungen: Klick-Statistiken, Hervorhebungen (Premium-Listing).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class MarketplaceListing extends Model
{
    use CentralConnection;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'watch_id',
        'seller_name',
        'seller_type',
        'shop_url',
        'detail_url',
        'brand_name',
        'model_name',
        'reference_number',
        'year_label',
        'condition_label',
        'material_label',
        'diameter_label',
        'has_box',
        'has_papers',
        'price',
        'previous_price',
        'discount_percent',
        'photo_url',
        'listed_at',
    ];

    protected function casts(): array
    {
        return [
            'has_box' => 'boolean',
            'has_papers' => 'boolean',
            'price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'listed_at' => 'datetime',
        ];
    }

    /** Formatierter Preis für die Marktplatz-Kachel. */
    public function formattedPrice(): ?string
    {
        return $this->price !== null
            ? number_format((float) $this->price, 0, ',', '.').' €'
            : null;
    }

    /** Formatierter Streichpreis (bei Preissenkung). */
    public function formattedPreviousPrice(): ?string
    {
        return $this->previous_price !== null
            ? number_format((float) $this->previous_price, 0, ',', '.').' €'
            : null;
    }

    /** Anzeige-Label des Verkäufer-Typs (eBay-Prinzip). */
    public function sellerTypeLabel(): string
    {
        return $this->seller_type === 'private' ? 'Privat' : 'Gewerblich';
    }
}
