<?php

/**
 * =========================================================================
 * MarketplaceController — Zentraler Marktplatz (chrono-save.de)
 * =========================================================================
 *
 * Zweck:
 *   Öffentliche Marktplatz-Startseite auf den ZENTRALEN Domains:
 *   alle aktiven Angebote aller Verkäufer (privat UND gewerblich,
 *   eBay-Prinzip) aus dem Listings-Spiegel (marketplace_listings).
 *   Jede Kachel verlinkt in den Shop des jeweiligen Verkäufers —
 *   Kauf, Anfrage und Preisvorschlag laufen dort.
 *
 * Verantwortlichkeiten:
 *   - Listing mit Freitextsuche, Marken-/Verkäufertyp-Filter, Sortierung.
 *   - KEINE Tenant-Datenbankzugriffe — nur der zentrale Spiegel.
 *
 * Erweiterungen: Verkäufer-Profilseiten, Merkliste, Suchagenten.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MarketplaceListing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('suche', ''));
        $brand = (string) $request->query('marke', '');
        $sellerType = (string) $request->query('verkaeufer', '');
        $sort = (string) $request->query('sortierung', 'neueste');

        $query = MarketplaceListing::query()
            ->when($search !== '', function ($query) use ($search) {
                $terms = preg_split('/\s+/', $search) ?: [];

                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $query->where(function ($q) use ($like) {
                        $q->where('brand_name', 'like', $like)
                            ->orWhere('model_name', 'like', $like)
                            ->orWhere('reference_number', 'like', $like);
                    });
                }
            })
            ->when($brand !== '', fn ($query) => $query->where('brand_name', $brand))
            ->when(
                in_array($sellerType, ['private', 'commercial'], true),
                fn ($query) => $query->where('seller_type', $sellerType),
            );

        match ($sort) {
            'preis_auf' => $query->orderByRaw('price IS NULL')->orderBy('price'),
            'preis_ab' => $query->orderByRaw('price IS NULL')->orderByDesc('price'),
            default => $query->orderByDesc('listed_at'),
        };

        $listings = $query->paginate(24)->withQueryString();

        // Markenfilter: nur Marken mit aktiven Angeboten
        $brands = MarketplaceListing::query()
            ->select('brand_name')
            ->distinct()
            ->orderBy('brand_name')
            ->pluck('brand_name');

        return view('marketplace.index', [
            'listings' => $listings,
            'brands' => $brands,
            'search' => $search,
            'filters' => [
                'marke' => $brand !== '' ? $brand : null,
                'verkaeufer' => $sellerType !== '' ? $sellerType : null,
                'suche' => $search !== '' ? $search : null,
                'sortierung' => $sort,
            ],
        ]);
    }
}
