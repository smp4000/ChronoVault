<?php

/**
 * =========================================================================
 * InventoryReportService — Bestands- und Wertübersicht (Versicherung)
 * =========================================================================
 *
 * Zweck:
 *   Erstellt die Bestandsliste als PDF für Versicherungen & Co.: alle
 *   Uhren im Bestand mit Foto, Referenz, SERIENNUMMER, Baujahr, Zustand,
 *   Lieferumfang und Wiederbeschaffungswert + Gesamtsumme und Stichtag.
 *
 * Wert-Logik (Wiederbeschaffung):
 *   current_market_value (nächtliche Wertermittlung) → sonst
 *   asking_price → sonst purchase_price. Die Quelle wird je Zeile
 *   ausgewiesen, damit die Liste belastbar bleibt.
 *
 * Bestand = alles außer „Verkauft"; Kommissionsuhren (Fremdeigentum)
 * optional zuschaltbar und als solche gekennzeichnet.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Models\Watch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Throwable;

class InventoryReportService
{
    /**
     * Datenbasis des Reports.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: float, count: int, generatedAt: Carbon, includePurchase: bool, includeConsignment: bool}
     */
    public function data(bool $includeConsignment = false, bool $includePurchase = false): array
    {
        $statuses = [
            WatchStatus::InStock->value,
            WatchStatus::Reserved->value,
            WatchStatus::InService->value,
            WatchStatus::InAuction->value,
            // Private Sammlung: Eigentum, versichert — gehört in die Liste
            WatchStatus::PrivateCollection->value,
        ];

        if ($includeConsignment) {
            $statuses[] = WatchStatus::Consignment->value;
        }

        $watches = Watch::query()
            ->whereIn('status', $statuses)
            ->with(['brand', 'media'])
            ->get()
            ->sortBy(fn (Watch $watch): string => $watch->brand->name.' '.$watch->model_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $rows = [];
        $total = 0.0;

        foreach ($watches as $watch) {
            [$value, $valueSource] = $this->replacementValue($watch);
            $total += $value ?? 0.0;

            $condition = $watch->getAttribute('condition');
            $status = $watch->getAttribute('status');

            $rows[] = [
                'name' => $watch->fullName(),
                'reference' => $watch->reference_number,
                'serial' => $watch->serial_number,
                'year' => $watch->production_year
                    ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year
                    : null,
                'condition' => $condition instanceof WatchCondition ? $condition->getLabel() : null,
                'scope' => implode(', ', array_filter([
                    $watch->has_box ? 'Box' : null,
                    $watch->has_papers ? 'Papiere' : null,
                ])) ?: null,
                'isConsignment' => $status === WatchStatus::Consignment,
                'purchasePrice' => $includePurchase ? $watch->purchase_price : null,
                'value' => $value,
                'valueSource' => $valueSource,
                'thumb' => $this->thumbBase64($watch),
            ];
        }

        return [
            'rows' => $rows,
            'total' => round($total, 2),
            'count' => count($rows),
            'generatedAt' => now(),
            'includePurchase' => $includePurchase,
            'includeConsignment' => $includeConsignment,
        ];
    }

    /**
     * PDF rendern (dompdf).
     */
    public function renderPdf(bool $includeConsignment = false, bool $includePurchase = false): string
    {
        return Pdf::loadView('pdf.inventory', [
            'report' => $this->data($includeConsignment, $includePurchase),
            'seller' => [
                'name' => (string) tenant('name'),
                'street' => tenant('company_street'),
                'postal_code' => tenant('company_postal_code'),
                'city' => tenant('company_city'),
            ],
        ])->output();
    }

    /**
     * Wiederbeschaffungswert + Quelle (Marktwert → Angebotspreis → EK).
     *
     * @return array{0: float|null, 1: string|null}
     */
    private function replacementValue(Watch $watch): array
    {
        if ($watch->current_market_value !== null) {
            return [(float) $watch->current_market_value, 'Marktwert'];
        }

        if ($watch->asking_price !== null) {
            return [(float) $watch->asking_price, 'Angebotspreis'];
        }

        if ($watch->purchase_price !== null) {
            return [(float) $watch->purchase_price, 'Einkaufspreis'];
        }

        return [null, null];
    }

    /**
     * Kleines JPEG-Thumbnail (Base64) fürs PDF — dompdf wird mit den
     * Original-Fotos zu langsam/groß. Fehler liefern null (kein Bild).
     */
    private function thumbBase64(Watch $watch): ?string
    {
        try {
            $media = $watch->getFirstMedia('photos');

            if ($media === null || ! is_file($media->getPath())) {
                return null;
            }

            $image = @imagecreatefromstring((string) file_get_contents($media->getPath()));

            if ($image === false) {
                return null;
            }

            $thumb = imagescale($image, 140);
            imagedestroy($image);

            if ($thumb === false) {
                return null;
            }

            ob_start();
            imagejpeg($thumb, null, 70);
            $jpeg = (string) ob_get_clean();
            imagedestroy($thumb);

            return $jpeg !== '' ? base64_encode($jpeg) : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
