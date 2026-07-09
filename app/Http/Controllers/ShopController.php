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
 *   - Listing/Detail im Scope visibleInShop(): auch Reserviert/In
 *     Auktion/Verkauft bleiben mit Badge sichtbar (Referenzen).
 *   - Kaufen/Anfragen nur im Scope publishedInShop() (kaufbar).
 *   - Markenfilter + Pagination fürs Listing.
 *   - 404 für unveröffentlichte Uhren — bewusst KEIN 403,
 *     Interna bleiben unsichtbar.
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

use App\Actions\Shop\PurchaseWatchAction;
use App\Enums\UserRole;
use App\Http\Requests\PurchaseWatchRequest;
use App\Http\Requests\WatchInquiryRequest;
use App\Mail\WatchInquiryMail;
use App\Models\Brand;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ShopController extends Controller
{
    /**
     * Shop-Startseite: Grid aller veröffentlichten Uhren,
     * optional nach Marke gefiltert (?marke=<brand_id>).
     */
    public function index(Request $request): View
    {
        $brandId = $request->query('marke');

        // Kaufbare zuerst, dann Reserviert/In Auktion, Verkauft ans Ende —
        // Besucher sehen das Verfügbare, Verkauftes bleibt als Referenz.
        $watches = Watch::query()
            ->visibleInShop()
            ->with(['brand', 'media'])
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->orderByRaw("CASE WHEN status IN ('in_stock', 'consignment') THEN 0 WHEN status IN ('reserved', 'in_auction') THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        // Markenfilter zeigt nur Marken, die aktuell im Shop vertreten sind —
        // ein leerer Filter-Eintrag wäre eine Sackgasse für Besucher.
        $shopBrandIds = Watch::query()
            ->visibleInShop()
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
     * Detailseite einer Uhr — für alle im Shop sichtbaren Uhren
     * erreichbar (auch Verkauft/Reserviert, dann mit Badge statt
     * Kauf-Button). Unveröffentlichtes ist ein sauberes 404.
     */
    public function show(string $watchId): View
    {
        $watch = Watch::query()
            ->visibleInShop()
            ->with(['brand', 'caliber', 'media'])
            ->findOrFail($watchId);

        // Weitere Uhren desselben Händlers als "Das könnte Sie auch
        // interessieren" — bevorzugt dieselbe Marke, nur Kaufbares.
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
     * Kaufseite: verbindlicher Sofortkauf zum Festpreis (nur für
     * veröffentlichte, verkäufliche Uhren MIT Preis — sonst 404).
     */
    public function buy(string $watchId): View
    {
        $watch = Watch::query()
            ->publishedInShop()
            ->whereNotNull('asking_price')
            ->with(['brand', 'media'])
            ->findOrFail($watchId);

        return view('shop.buy', ['watch' => $watch]);
    }

    /**
     * Kauf ausführen — Uhr reservieren, Kontakt anlegen, Mails senden.
     * Fachliche Ablehnung (inzwischen verkauft/reserviert) erscheint
     * als Formularfehler.
     */
    public function purchase(PurchaseWatchRequest $request, string $watchId): RedirectResponse
    {
        $watch = Watch::query()
            ->with('brand')
            ->findOrFail($watchId);

        try {
            app(PurchaseWatchAction::class)->execute($watch, $request->validated());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('shop.show', $watch)
                ->withErrors(['purchase' => $exception->getMessage()]);
        }

        // NICHT back(): Die Uhr ist jetzt reserviert — Kauf- und
        // Detailseite existieren für sie nicht mehr (404). Erfolg
        // zeigt der Shop-Katalog als Banner.
        return redirect()
            ->route('shop.index')
            ->with(
                'purchase_success',
                'Vielen Dank für Ihren Kauf! Die Uhr ist für Sie reserviert — die Kaufbestätigung mit den Zahlungsinformationen ist auf dem Weg in Ihr Postfach.'
            );
    }

    /**
     * Kaufanfrage zu einer Uhr — geht per Mail an die Inhaber des
     * Betriebs (Reply-To: Kunde). Bewusst KEIN automatischer Kontakt
     * im Kundenstamm: Erst eine echte Geschäftsbeziehung (Kauf/Gebot)
     * macht Interessenten zu Kontakten.
     */
    public function inquire(WatchInquiryRequest $request, string $watchId): RedirectResponse
    {
        // Bewusst visibleInShop: Auch zu verkauften/reservierten Uhren
        // sind Anfragen willkommen (Interesse an vergleichbaren Stücken).
        $watch = Watch::query()
            ->visibleInShop()
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
