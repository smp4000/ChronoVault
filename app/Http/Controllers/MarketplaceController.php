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

use App\Http\Requests\PriceProposalRequest;
use App\Http\Requests\WatchInquiryRequest;
use App\Mail\PriceProposalMail;
use App\Mail\WatchInquiryMail;
use App\Models\MarketplaceListing;
use App\Models\PriceProposal;
use App\Models\Tenant;
use App\Models\Watch;
use App\Support\TenantNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Zentrale Angebotsseite (Sammelstelle, eBay-Prinzip) — vor allem für
     * PRIVATE Verkäufer ohne eigenen Shop: Galerie, Daten, Anfrage und
     * Preisvorschlag laufen direkt auf der Plattform.
     */
    public function show(string $listingId): View
    {
        $listing = MarketplaceListing::query()->findOrFail($listingId);

        return view('marketplace.show', [
            'listing' => $listing,
            'search' => '',
            'capA' => random_int(1, 9),
            'capB' => random_int(1, 9),
        ]);
    }

    /**
     * Anfrage zu einem zentralen Angebot — läuft im Kontext des
     * Verkäufer-Mandanten (Mail an dessen Benachrichtigungs-Adresse).
     */
    public function inquire(WatchInquiryRequest $request, string $listingId): RedirectResponse
    {
        $listing = MarketplaceListing::query()->findOrFail($listingId);
        $validated = $request->validated();

        $this->sellerTenant($listing)->run(function () use ($listing, $validated): void {
            $watch = Watch::query()
                ->visibleInShop()
                ->with('brand')
                ->findOrFail($listing->watch_id);

            Mail::to(TenantNotifications::recipients())
                ->send(new WatchInquiryMail($watch, $validated));
        });

        return back()->with(
            'inquiry_success',
            'Vielen Dank für Ihre Anfrage — der Verkäufer meldet sich schnellstmöglich bei Ihnen.'
        );
    }

    /**
     * Preisvorschlag zu einem zentralen Angebot — persistiert in der
     * Verkäufer-DB (Panel-Workflow inkl. Gegenangebot funktioniert
     * damit auch für Privatverkäufer).
     */
    public function propose(PriceProposalRequest $request, string $listingId): RedirectResponse
    {
        $listing = MarketplaceListing::query()->findOrFail($listingId);
        $validated = $request->validated();

        $this->sellerTenant($listing)->run(function () use ($listing, $validated): void {
            $watch = Watch::query()
                ->publishedInShop()
                ->with('brand')
                ->findOrFail($listing->watch_id);

            PriceProposal::create([
                'watch_id' => $watch->getKey(),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'proposed_price' => $validated['proposed_price'],
                'asking_price_at_time' => $watch->getAttribute('asking_price'),
                'message' => $validated['message'] ?? null,
            ]);

            Mail::to(TenantNotifications::recipients())
                ->send(new PriceProposalMail($watch, $validated));
        });

        return back()->with(
            'proposal_success',
            'Vielen Dank für Ihren Preisvorschlag — der Verkäufer meldet sich schnellstmöglich bei Ihnen.'
        );
    }

    /** Verkäufer-Mandant eines Listings (404 statt Interna bei Waisen). */
    private function sellerTenant(MarketplaceListing $listing): Tenant
    {
        return Tenant::query()->findOrFail($listing->tenant_id);
    }
}
