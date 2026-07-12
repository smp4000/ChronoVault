<?php

/**
 * =========================================================================
 * SyncWatchToMarketplaceAction — Uhr in den zentralen Marktplatz spiegeln
 * =========================================================================
 *
 * Zweck:
 *   Hält die zentrale marketplace_listings-Tabelle synchron zur Uhr in
 *   der Tenant-DB: Veröffentlichte, KAUFBARE Uhren (Lager/Kommission)
 *   werden gelistet — alles andere (verkauft, reserviert, unveröffent-
 *   licht, im Service, Wunschliste, Sammlung, gelöscht) fliegt raus.
 *   Auf dem Marktplatz erscheinen also nur aktive Angebote (eBay-
 *   Prinzip); der eigene Händler-Shop zeigt Verkauftes weiter als
 *   Referenz.
 *
 * Verantwortlichkeiten:
 *   - Anzeige-Daten denormalisieren (Marke, Labels, Preise, Foto-URL).
 *   - URLs auf die Domain des Verkäufers bauen — auch im CLI-Kontext
 *     (marketplace:sync) korrekt: Host-Teil der generierten URLs wird
 *     durch die Verkäufer-Domain ersetzt (kein forceRootUrl!).
 *   - Fehler dürfen NIE das Speichern der Uhr blockieren (report + weiter).
 *
 * Nutzung: WatchObserver (saved/deleted/restored) + marketplace:sync.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Marketplace;

use App\Enums\CaseMaterial;
use App\Enums\WatchCondition;
use App\Models\MarketplaceListing;
use App\Models\Watch;
use Throwable;

class SyncWatchToMarketplaceAction
{
    /**
     * Spiegel-Zeile anlegen/aktualisieren oder entfernen.
     */
    public function execute(Watch $watch): void
    {
        $tenant = tenant();

        if ($tenant === null) {
            return;
        }

        try {
            $shouldList = ! $watch->trashed()
                && (bool) $watch->getAttribute('is_published')
                && $watch->isBuyableInShop();

            if (! $shouldList) {
                MarketplaceListing::query()
                    ->where('tenant_id', (string) $tenant->getTenantKey())
                    ->where('watch_id', (string) $watch->getKey())
                    ->delete();

                return;
            }

            $domain = method_exists($tenant, 'primaryDomain') ? $tenant->primaryDomain() : null;

            if ($domain === null) {
                return; // Ohne Domain kein verlinkbares Angebot
            }

            // Produktions-Domains laufen hinter Cloudflare immer auf https
            $base = (app()->isProduction() ? 'https://' : 'http://').$domain;

            $watch->loadMissing(['brand', 'media']);

            $condition = $watch->getAttribute('condition');
            $material = $watch->getAttribute('case_material');

            // eBay-Prinzip: privat ODER gewerblich (Tenant-Einstellung)
            $sellerType = (string) ($tenant->getAttribute('seller_type') ?? 'commercial');
            $photoUrls = $this->absolutePhotoUrls($watch, $base);

            $listing = MarketplaceListing::query()->updateOrCreate(
                [
                    'tenant_id' => (string) $tenant->getTenantKey(),
                    'watch_id' => (string) $watch->getKey(),
                ],
                [
                    'seller_name' => (string) ($tenant->getAttribute('name') ?? 'Verkäufer'),
                    'seller_type' => $sellerType,
                    'shop_url' => $base,
                    // Gewerblich: Detailseite im eigenen Shop des Händlers.
                    // Privat: zentrale Angebotsseite auf der Plattform
                    // (Sammelstelle) — wird nach dem Upsert gesetzt, weil
                    // die Listing-ID erst dann feststeht.
                    'detail_url' => $base.'/uhren/'.$watch->getKey(),
                    'brand_name' => $watch->brand->name,
                    'model_name' => (string) $watch->model_name,
                    'reference_number' => $watch->reference_number,
                    'year_label' => $watch->production_year
                        ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year
                        : null,
                    'condition_label' => $condition instanceof WatchCondition ? $condition->getLabel() : null,
                    'material_label' => $material instanceof CaseMaterial ? $material->getLabel() : null,
                    'diameter_label' => $watch->case_diameter_mm
                        ? rtrim(rtrim(number_format((float) $watch->case_diameter_mm, 1, ',', '.'), '0'), ',').' mm'
                        : null,
                    'has_box' => (bool) $watch->has_box,
                    'has_papers' => (bool) $watch->has_papers,
                    'price' => $watch->asking_price,
                    'previous_price' => $watch->previous_asking_price,
                    'discount_percent' => $watch->discountPercent(),
                    'photo_url' => $photoUrls[0] ?? null,
                    'description' => filled($watch->description) ? (string) $watch->description : null,
                    'photo_urls' => $photoUrls,
                    // Sofortkauf: je Uhr abschaltbar; Privatverkäufer
                    // brauchen zusätzlich eine hinterlegte IBAN (der
                    // Käufer überweist direkt an sie)
                    'direct_buy' => (bool) $watch->getAttribute('allow_direct_buy')
                        && ($sellerType !== 'private' || filled($tenant->getAttribute('bank_iban'))),
                    'listed_at' => $watch->created_at ?? now(),
                ],
            );

            // Privat-Angebote laufen über die zentrale Angebotsseite
            if ($sellerType === 'private') {
                $listing->update(['detail_url' => $this->centralBaseUrl().'/angebot/'.$listing->getKey()]);
            }
        } catch (Throwable $exception) {
            // Marktplatz-Sync darf das Arbeiten im Panel nie blockieren
            report($exception);
        }
    }

    /**
     * Alle Spiegel-Zeilen eines Mandanten entfernen, deren Uhren es
     * nicht mehr in den Marktplatz schaffen (Aufräumen beim Backfill).
     *
     * @param  array<int, string>  $keepWatchIds
     */
    public function pruneTenant(string $tenantId, array $keepWatchIds): void
    {
        MarketplaceListing::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('watch_id', $keepWatchIds)
            ->delete();
    }

    /**
     * Foto-URL mit der Domain des Verkäufers — im Web-Request stimmt die
     * Root-URL bereits, im CLI (Backfill) wird sie temporär erzwungen.
     */
    /**
     * ALLE Foto-URLs der Uhr mit der Domain des Verkäufers.
     * Bewusst OHNE URL::forceRootUrl (verstellt die Root-URL des
     * Prozesses) — stattdessen wird der Host-Teil ersetzt.
     *
     * @return array<int, string>
     */
    private function absolutePhotoUrls(Watch $watch, string $base): array
    {
        return array_values(array_map(function (string $url) use ($base): string {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            $query = parse_url($url, PHP_URL_QUERY);

            return $base.$path.(is_string($query) && $query !== '' ? '?'.$query : '');
        }, $watch->photoUrls()));
    }

    /**
     * Basis-URL der zentralen Plattform (Marktplatz) — Produktions-Domain
     * aus CENTRAL_DOMAIN, lokal/Tests localhost.
     */
    private function centralBaseUrl(): string
    {
        // Erste "echte" Central-Domain (Produktions-Domain aus der .env,
        // via config/tenancy.php) — lokal bleiben nur localhost/127.0.0.1.
        // KEIN env() zur Laufzeit: unter config:cache liefert das null.
        $domains = (array) config('tenancy.central_domains', []);

        $production = array_values(array_filter(
            $domains,
            fn ($domain): bool => ! in_array($domain, ['localhost', '127.0.0.1'], true),
        ));

        $domain = (string) ($production[0] ?? 'localhost');

        return (app()->isProduction() ? 'https://' : 'http://').$domain;
    }
}
