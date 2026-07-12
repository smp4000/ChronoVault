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

            MarketplaceListing::query()->updateOrCreate(
                [
                    'tenant_id' => (string) $tenant->getTenantKey(),
                    'watch_id' => (string) $watch->getKey(),
                ],
                [
                    'seller_name' => (string) ($tenant->getAttribute('name') ?? 'Verkäufer'),
                    // eBay-Prinzip: privat ODER gewerblich (Tenant-Einstellung)
                    'seller_type' => (string) ($tenant->getAttribute('seller_type') ?? 'commercial'),
                    'shop_url' => $base,
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
                    'photo_url' => $this->absolutePhotoUrl($watch, $base),
                    'listed_at' => $watch->created_at ?? now(),
                ],
            );
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
    private function absolutePhotoUrl(Watch $watch, string $base): ?string
    {
        $url = $watch->firstPhotoUrl();

        if ($url === null) {
            return null;
        }

        // Nur Pfad+Query behalten und die Verkäufer-Domain davorsetzen —
        // bewusst OHNE URL::forceRootUrl: das würde die Root-URL für den
        // restlichen Prozess verstellen (CLI-Sync, nachfolgende Requests).
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $query = parse_url($url, PHP_URL_QUERY);

        return $base.$path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
