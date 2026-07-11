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

use App\Actions\Shop\AcceptPriceProposalAction;
use App\Actions\Shop\DeclinePriceProposalAction;
use App\Actions\Shop\PurchaseWatchAction;
use App\Enums\PriceProposalStatus;
use App\Enums\UserRole;
use App\Http\Requests\PriceProposalRequest;
use App\Http\Requests\PurchaseWatchRequest;
use App\Http\Requests\WatchInquiryRequest;
use App\Mail\PriceProposalMail;
use App\Mail\WatchInquiryMail;
use App\Models\Brand;
use App\Models\PriceProposal;
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
    /** Durchmesser-Filterbereiche: Schlüssel => [min, max] in mm. */
    private const DIAMETER_RANGES = [
        'bis36' => [null, 36],
        '36-40' => [36, 40],
        'ab40' => [40, null],
    ];

    /** Preis-Filterbereiche: Schlüssel => [min, max] in Euro. */
    private const PRICE_RANGES = [
        'bis1000' => [null, 1000],
        '1000-5000' => [1000, 5000],
        '5000-10000' => [5000, 10000],
        'ab10000' => [10000, null],
    ];

    public function index(Request $request): View
    {
        $brandId = $request->query('marke');
        $condition = $request->query('zustand');
        $material = $request->query('material');
        $diameter = $request->query('durchmesser');
        $price = $request->query('preis');
        $sort = (string) $request->query('sortierung', 'neueste');

        // Kaufbare zuerst, dann Reserviert/In Auktion, Verkauft ans Ende —
        // Besucher sehen das Verfügbare, Verkauftes bleibt als Referenz.
        $query = Watch::query()
            ->visibleInShop()
            ->with(['brand', 'media'])
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($condition, fn ($query) => $query->where('condition', $condition))
            ->when($material, fn ($query) => $query->where('case_material', $material))
            ->when(
                is_string($diameter) && isset(self::DIAMETER_RANGES[$diameter]),
                function ($query) use ($diameter) {
                    [$min, $max] = self::DIAMETER_RANGES[$diameter];
                    $query->when($min !== null, fn ($q) => $q->where('case_diameter_mm', '>=', $min))
                        ->when($max !== null, fn ($q) => $q->where('case_diameter_mm', '<', $max));
                },
            )
            ->when(
                is_string($price) && isset(self::PRICE_RANGES[$price]),
                function ($query) use ($price) {
                    [$min, $max] = self::PRICE_RANGES[$price];
                    $query->whereNotNull('asking_price')
                        ->when($min !== null, fn ($q) => $q->where('asking_price', '>=', $min))
                        ->when($max !== null, fn ($q) => $q->where('asking_price', '<', $max));
                },
            )
            ->orderByRaw("CASE WHEN status IN ('in_stock', 'consignment') THEN 0 WHEN status IN ('reserved', 'in_auction') THEN 1 ELSE 2 END");

        // Sortierung: Preis-Sortierungen stellen preislose Uhren ans Ende
        match ($sort) {
            'preis_auf' => $query->orderByRaw('asking_price IS NULL')->orderBy('asking_price'),
            'preis_ab' => $query->orderByRaw('asking_price IS NULL')->orderByDesc('asking_price'),
            default => $query->orderByDesc('created_at'),
        };

        $watches = $query->paginate(12)->withQueryString();

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
            'filters' => [
                'zustand' => $condition,
                'material' => $material,
                'durchmesser' => $diameter,
                'preis' => $price,
                'sortierung' => $sort,
            ],
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
     * Preisvorschlag zu einer Uhr — geht wie die Anfrage per Mail an die
     * Inhaber (Reply-To: Kunde). Nur für kaufbare Uhren sinnvoll (404 sonst).
     */
    public function propose(PriceProposalRequest $request, string $watchId): RedirectResponse
    {
        $watch = Watch::query()
            ->publishedInShop()
            ->with('brand')
            ->findOrFail($watchId);

        $validated = $request->validated();

        // Zusätzlich zur Mail persistieren — der Händler sieht und
        // beantwortet Vorschläge im Panel (Preisvorschläge-Ressource).
        PriceProposal::create([
            'watch_id' => $watch->getKey(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'proposed_price' => $validated['proposed_price'],
            'asking_price_at_time' => $watch->getAttribute('asking_price'),
            'message' => $validated['message'] ?? null,
        ]);

        Mail::to($this->inquiryRecipients())
            ->send(new PriceProposalMail($watch, $validated));

        return back()->with(
            'proposal_success',
            'Vielen Dank für Ihren Preisvorschlag — wir melden uns schnellstmöglich bei Ihnen.'
        );
    }

    /**
     * Kunden-Entscheidung zum Gegenangebot (signierter Link aus der
     * CounterOfferMail): Annahme wickelt den Kauf zum Gesamtpreis
     * (Angebot + Versand) komplett ab — Verkauf, Rechnung, Kaufvertrag,
     * Zahlungs-Mail. Ablehnung schließt den Vorgang und schickt eine
     * freundliche „Schade"-Mail.
     */
    public function proposalDecision(string $proposalId, string $decision): View
    {
        $proposal = PriceProposal::query()
            ->with('watch.brand')
            ->findOrFail($proposalId);

        $status = $proposal->getAttribute('status');

        // Bereits abgeschlossen (z. B. Link doppelt geklickt)?
        if (! $status instanceof PriceProposalStatus || ! $status->isOpen()) {
            return view('shop.proposal-decision', [
                'success' => $status === PriceProposalStatus::Accepted,
                'heading' => 'Dieser Vorgang ist bereits abgeschlossen',
                'text' => 'Ihre Entscheidung wurde schon verarbeitet — bei Fragen antworten Sie einfach auf unsere E-Mail.',
            ]);
        }

        if ($decision === 'annehmen') {
            $total = $proposal->counterTotal() ?? (float) $proposal->proposed_price;
            $shipping = (float) ($proposal->getAttribute('shipping_price') ?? 0);

            try {
                app(AcceptPriceProposalAction::class)->execute(
                    $proposal,
                    [],
                    $total,
                    $shipping > 0
                        ? 'Gegenangebot angenommen: Uhr '.number_format((float) $proposal->counter_price, 2, ',', '.').' € + Versand '.number_format($shipping, 2, ',', '.').' €.'
                        : 'Gegenangebot angenommen.',
                );
            } catch (RuntimeException) {
                return view('shop.proposal-decision', [
                    'success' => false,
                    'heading' => 'Diese Uhr ist leider nicht mehr verfügbar',
                    'text' => 'Die Uhr wurde zwischenzeitlich anderweitig verkauft. Antworten Sie gerne auf unsere E-Mail — wir halten Ausschau nach einem vergleichbaren Stück.',
                ]);
            }

            return view('shop.proposal-decision', [
                'success' => true,
                'heading' => 'Vielen Dank — der Kauf ist verbindlich zustande gekommen!',
                'text' => 'Sie erhalten in wenigen Minuten eine E-Mail mit den Zahlungsinformationen, Ihrer Rechnung und dem Kaufvertrag. Nach Zahlungseingang versenden wir Ihre Uhr.',
            ]);
        }

        // Ablehnung: Vorgang schließen + freundliche „Schade"-Mail
        // (gleiche Action wie der Ablehnen-Knopf im Panel)
        app(DeclinePriceProposalAction::class)->execute($proposal);

        return view('shop.proposal-decision', [
            'success' => false,
            'heading' => 'Schade — vielleicht beim nächsten Mal',
            'text' => 'Wir haben Ihre Entscheidung vermerkt. Schauen Sie gerne wieder in unserer Kollektion vorbei — vielleicht ist bald das passende Stück für Sie dabei.',
        ]);
    }

    /**
     * Rechtsseiten (Impressum/Datenschutz/Widerruf) — Inhalte kommen
     * aus den Betriebsdaten des Händlers (Tenant-data-JSON). Fehlender
     * Text zeigt einen deutlichen Hinweis statt einer leeren Seite.
     */
    public function legal(string $page): View
    {
        [$title, $tenantKey] = match ($page) {
            'imprint' => ['Impressum', 'imprint'],
            'privacy' => ['Datenschutzerklärung', 'privacy_policy'],
            default => ['Widerrufsbelehrung', 'revocation_policy'],
        };

        $content = tenant($tenantKey);

        return view('shop.legal', [
            'title' => $title,
            'content' => is_string($content) && trim($content) !== '' ? $content : null,
        ]);
    }

    /**
     * Empfänger der Anfrage: die in den Betriebsdaten hinterlegte
     * Benachrichtigungs-Adresse; sonst Inhaber, dann Administratoren,
     * als letzter Fallback die Absenderadresse der Plattform.
     *
     * @return array<int, string>
     */
    private function inquiryRecipients(): array
    {
        $configured = tenant('notification_email');

        if (is_string($configured) && $configured !== '') {
            return [$configured];
        }

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
