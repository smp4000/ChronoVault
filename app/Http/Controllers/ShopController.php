<?php

/**
 * =========================================================================
 * ShopController — Öffentliches Schaufenster eines Mandanten
 * =========================================================================
 *
 * Zweck:
 *   Liefert den öffentlichen Shop auf der Tenant-Domain (z. B.
 *   welle.localhost): Listing aller veröffentlichten, verkäuflichen
 *   Uhren + Detailseite. Design-Referenz: docs/DESIGN.md
 *   (grimmeissen.de-Stil, Blau als Akzentfarbe).
 *
 * Verantwortlichkeiten:
 *   - Nur Uhren im Scope publishedInShop() ausliefern (Opt-in des
 *     Händlers, verkäuflicher Status, keine Soft-Deleted).
 *   - Markenfilter + Pagination fürs Listing.
 *   - 404 für unveröffentlichte/verkaufte Uhren auf der Detailseite —
 *     bewusst KEIN 403, Interna bleiben unsichtbar.
 *
 * Abhängigkeiten:
 *   - Tenancy ist durch die Routen-Middleware bereits initialisiert;
 *     alle Queries laufen automatisch auf der Tenant-Datenbank.
 *
 * Mögliche Erweiterungen:
 *   - Anfrage-Formular (Lead-Erfassung als Contact, Modul 5)
 *   - Sortierung/Preisfilter, Merkliste
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\WatchInquiryRequest;
use App\Mail\WatchInquiryMail;
use App\Models\Brand;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ShopController extends Controller
{
    /**
     * Shop-Startseite: Grid aller veröffentlichten Uhren,
     * optional nach Marke gefiltert (?marke=<brand_id>).
     */
    public function index(Request $request): View
    {
        $brandId = $request->query('marke');

        $watches = Watch::query()
            ->publishedInShop()
            ->with(['brand', 'media'])
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        // Markenfilter zeigt nur Marken, die aktuell im Shop vertreten sind —
        // ein leerer Filter-Eintrag wäre eine Sackgasse für Besucher.
        $shopBrandIds = Watch::query()
            ->publishedInShop()
            ->distinct()
            ->pluck('brand_id');

        $brands = Brand::query()
            ->whereIn('id', $shopBrandIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('shop.index', [
            'watches' => $watches,
            'brands' => $brands,
            'activeBrandId' => $brandId,
        ]);
    }

    /**
     * Detailseite einer Uhr — nur für veröffentlichte, verkäufliche
     * Uhren erreichbar, alles andere ist ein sauberes 404.
     */
    public function show(string $watchId): View
    {
        $watch = Watch::query()
            ->publishedInShop()
            ->with(['brand', 'caliber', 'media'])
            ->findOrFail($watchId);

        // Weitere Uhren desselben Händlers als "Das könnte Sie auch
        // interessieren" — bevorzugt dieselbe Marke.
        $related = Watch::query()
            ->publishedInShop()
            ->with(['brand', 'media'])
            ->whereKeyNot($watch->getKey())
            ->orderByRaw('CASE WHEN brand_id = ? THEN 0 ELSE 1 END', [$watch->brand_id])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        return view('shop.show', [
            'watch' => $watch,
            'related' => $related,
        ]);
    }

    /**
     * Kaufanfrage zu einer Uhr — geht per Mail an die Inhaber des
     * Betriebs (Reply-To: Kunde). Bewusst KEIN automatischer Kontakt
     * im Kundenstamm: Erst eine echte Geschäftsbeziehung (Kauf/Gebot)
     * macht Interessenten zu Kontakten.
     */
    public function inquire(WatchInquiryRequest $request, string $watchId): RedirectResponse
    {
        $watch = Watch::query()
            ->publishedInShop()
            ->with('brand')
            ->findOrFail($watchId);

        Mail::to($this->inquiryRecipients())
            ->send(new WatchInquiryMail($watch, $request->validated()));

        return back()->with(
            'inquiry_success',
            'Vielen Dank für Ihre Anfrage — wir melden uns schnellstmöglich bei Ihnen.'
        );
    }

    /**
     * Empfänger der Anfrage: Inhaber des Betriebs, sonst Administratoren,
     * als letzter Fallback die Absenderadresse der Plattform.
     *
     * @return array<int, string>
     */
    private function inquiryRecipients(): array
    {
        $owners = User::role(UserRole::Owner->value)->pluck('email')->all();

        if ($owners !== []) {
            return $owners;
        }

        $admins = User::role(UserRole::Admin->value)->pluck('email')->all();

        if ($admins !== []) {
            return $admins;
        }

        return [(string) config('mail.from.address')];
    }
}
