<?php

/**
 * =========================================================================
 * InventoryReportService — Bestands- und Wertübersicht (Versicherung)
 * =========================================================================
 *
 * Zweck:
 *   Erstellt die Versicherungs-Dokumentation als PDF: je Uhr ein
 *   vollständiger Block nach Versicherungs-Checkliste — Hersteller,
 *   Modell, Referenz, SERIENNUMMER (optional teilgeschwärzt),
 *   Kaufdatum/-preis, aktueller Wert mit Quelle, Zertifikat/Papiere,
 *   Zubehör, Besonderheiten (Limitierung, Revisionen), Beleg-Nachweis
 *   und MEHRERE Fotos (alle Slot-Perspektiven + weitere).
 *
 * Wert-Logik (Wiederbeschaffung):
 *   current_market_value (nächtliche Wertermittlung) → sonst
 *   asking_price → sonst purchase_price. Die Quelle wird je Zeile
 *   ausgewiesen, damit die Liste belastbar bleibt.
 *
 *   UNTERGRENZE: Liegt der Marktwert UNTER dem Einkaufspreis, gilt
 *   stattdessen der Einkaufspreis plus Alterszuschlag (Wiederbe-
 *   schaffung deckt den Ersatz, nicht den Wiederverkauf):
 *   1. Jahr seit Kauf +10 %, 2. Jahr +15 %, ab dem 3. Jahr +20 %.
 *
 * Bestand = alles außer „Verkauft"/„Wunschliste"; Eigentum (Sammlung)
 * zählt immer mit; Kommission (Fremdeigentum) optional + gekennzeichnet.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\CaseMaterial;
use App\Enums\PhotoSlot;
use App\Enums\TransactionType;
use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Models\Watch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

class InventoryReportService
{
    /** Maximale Fotos je Uhr im PDF (Dateigröße/Lesbarkeit). */
    private const MAX_PHOTOS = 6;

    /** Fotos je Zertifikat: Titelbild + Foto-Dokumentation auf Seite 2. */
    private const CERT_MAX_PHOTOS = 7;

    /** Thumbnail-Breite (px) für Zertifikats-Fotos (Seite 2 zeigt sie groß). */
    private const CERT_PHOTO_WIDTH = 480;

    /**
     * Datenbasis des Reports.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: float, count: int, generatedAt: Carbon, includePurchase: bool, includeConsignment: bool}
     */
    public function data(bool $includeConsignment = false, bool $includePurchase = false, bool $maskSerial = false, int $photosPerRow = self::MAX_PHOTOS): array
    {
        $watches = $this->reportWatches($includeConsignment);

        $rows = [];
        $total = 0.0;

        foreach ($watches as $watch) {
            [$value, $valueSource] = $this->replacementValue($watch);
            $total += $value ?? 0.0;

            $condition = $watch->getAttribute('condition');
            $status = $watch->getAttribute('status');
            $purchaseDate = $watch->getAttribute('purchase_date');

            // Besonderheiten: Limitierung + Servicehistorie (Revisionen)
            $completedServices = $watch->serviceRecords
                ->filter(fn ($record): bool => $record->getAttribute('completed_at') !== null);
            $lastService = $completedServices->max(fn ($record) => $record->getAttribute('completed_at'));

            $specials = array_filter([
                $watch->is_limited_edition
                    ? trim('Limitierte Auflage '.($watch->limited_edition_number ? 'Nr. '.$watch->limited_edition_number : '').($watch->limited_edition_total ? ' von '.$watch->limited_edition_total : ''))
                    : null,
                $completedServices->isNotEmpty()
                    ? $completedServices->count().' Revision(en), zuletzt '.Carbon::parse($lastService)->format('m/Y')
                    : null,
            ]);

            $rows[] = [
                'brand' => $watch->brand->name,
                'model' => $watch->model_name,
                'name' => $watch->fullName(),
                'reference' => $watch->reference_number,
                'serial' => $this->serial($watch->serial_number, $maskSerial),
                'year' => $watch->production_year
                    ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year
                    : null,
                'condition' => $condition instanceof WatchCondition ? $condition->getLabel() : null,
                'scope' => implode(', ', array_filter([
                    $watch->has_box ? 'Originalbox' : null,
                    $watch->has_papers ? 'Papiere' : null,
                    $watch->delivery_scope,
                ])) ?: null,
                'certificate' => $watch->has_papers ? 'Garantiekarte/Papiere vorhanden' : 'nicht vorhanden',
                'purchaseDate' => $purchaseDate !== null ? Carbon::parse($purchaseDate)->format('d.m.Y') : null,
                'hasReceipt' => $watch->transactions
                    ->contains(fn ($transaction): bool => $transaction->getAttribute('type') === TransactionType::Purchase),
                'specials' => $specials !== [] ? implode(' · ', $specials) : null,
                'isConsignment' => $status === WatchStatus::Consignment,
                'isPrivate' => $status === WatchStatus::PrivateCollection,
                'purchasePrice' => $includePurchase ? $watch->purchase_price : null,
                'value' => $value,
                'valueSource' => $valueSource,
                'photos' => $this->photoThumbs($watch, $photosPerRow),
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
     * Wert-Zertifikat für EINE Uhr (PDF) — Versicherungs-Zertifikat-Stil:
     * Aussteller (Betrieb), Eigentümer, Uhr mit allen Kenndaten,
     * Versicherungswert (gleiche Wert-Logik wie die Bestandsliste)
     * und Foto-Dokumentation.
     */
    public function renderCertificatePdf(
        Watch $watch,
        ?string $issuedFor = null,
        bool $includePurchase = true,
        bool $maskSerial = false,
        bool $withDocuments = true,
    ): string {
        $cert = $this->certificateData($watch, $issuedFor, $includePurchase, $maskSerial, $withDocuments);

        $pdf = Pdf::loadView('pdf.certificate', [
            'cert' => $cert,
            'generatedAt' => now(),
            'seller' => $this->seller(),
        ])->output();

        // PDF-Belege (Original-Kaufrechnungen etc.) hinten anheften
        return $withDocuments
            ? $this->appendPdfDocuments($pdf, $this->documentPdfs($watch, $cert['certNumber']))
            : $pdf;
    }

    /**
     * Datenbasis EINES Zertifikats (genutzt vom Einzel-PDF und von der
     * Versicherungsmappe, die je Eigentums-Uhr ein Zertifikat anhängt).
     *
     * @return array{watch: array<string, mixed>, certNumber: string, issuedFor: string|null}
     */
    private function certificateData(
        Watch $watch,
        ?string $issuedFor,
        bool $includePurchase,
        bool $maskSerial,
        bool $withDocuments = false,
    ): array {
        $watch->loadMissing(['brand', 'caliber', 'media', 'serviceRecords']);

        [$value, $valueSource] = $this->replacementValue($watch);

        $condition = $watch->getAttribute('condition');
        $material = $watch->getAttribute('case_material');
        $purchaseDate = $watch->getAttribute('purchase_date');

        $completedServices = $watch->serviceRecords
            ->filter(fn ($record): bool => $record->getAttribute('completed_at') !== null);

        $specials = array_filter([
            $watch->is_limited_edition
                ? trim('Limitierte Auflage '.($watch->limited_edition_number ? 'Nr. '.$watch->limited_edition_number : '').($watch->limited_edition_total ? ' von '.$watch->limited_edition_total : ''))
                : null,
            $completedServices->isNotEmpty()
                ? $completedServices->count().' Revision(en), zuletzt '.Carbon::parse($completedServices->max(fn ($record) => $record->getAttribute('completed_at')))->format('m/Y')
                : null,
        ]);

        return [
            'watch' => [
                'name' => $watch->fullName(),
                'brand' => $watch->brand->name,
                'model' => $watch->model_name,
                'reference' => $watch->reference_number,
                'serial' => $this->serial($watch->serial_number, $maskSerial),
                'stockNumber' => $watch->stock_number,
                'year' => $watch->production_year
                    ? ($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year
                    : null,
                'condition' => $condition instanceof WatchCondition ? $condition->getLabel() : null,
                'caliber' => $watch->caliber?->name,
                'material' => $material instanceof CaseMaterial ? $material->getLabel() : null,
                'diameter' => $watch->case_diameter_mm
                    ? rtrim(rtrim(number_format((float) $watch->case_diameter_mm, 1, ',', '.'), '0'), ',').' mm'
                    : null,
                'scope' => implode(', ', array_filter([
                    $watch->has_box ? 'Originalbox' : null,
                    $watch->has_papers ? 'Papiere/Garantiekarte' : null,
                    $watch->delivery_scope,
                ])) ?: null,
                'specials' => $specials !== [] ? implode(' · ', $specials) : null,
                'purchaseDate' => $includePurchase && $purchaseDate !== null ? Carbon::parse($purchaseDate)->format('d.m.Y') : null,
                'purchasePrice' => $includePurchase ? $watch->purchase_price : null,
                'value' => $value,
                'valueSource' => $valueSource,
                'valuedAt' => $watch->getAttribute('last_valuation_at'),
                'photos' => $this->photoThumbs($watch, self::CERT_MAX_PHOTOS, self::CERT_PHOTO_WIDTH),
            ],
            // Zertifikat-Nr.: Lagernummer, sonst kompakte ID
            'certNumber' => $watch->stock_number ?? 'CV-'.strtoupper(mb_substr((string) $watch->getKey(), 0, 8)),
            'issuedFor' => $issuedFor,
            // Bild-Belege (fotografierte Kaufrechnungen etc.) als eigene Seiten
            'attachments' => $withDocuments ? $this->documentImages($watch) : [],
        ];
    }

    /**
     * Versicherungsmappe (PDF): vorne die Übersicht aller Uhren im
     * Eigentum, dahinter je Uhr das komplette Wert-Zertifikat
     * (Kommissionsware = Fremdeigentum bekommt KEIN Zertifikat).
     */
    public function renderPdf(
        bool $includeConsignment = false,
        bool $includePurchase = false,
        bool $maskSerial = false,
        bool $withCertificates = true,
        ?string $issuedFor = null,
        bool $withDocuments = true,
    ): string {
        // Übersicht: nur je ein Titelbild pro Zeile (die Foto-Doku steckt im Zertifikat)
        $report = $this->data($includeConsignment, $includePurchase, $maskSerial, photosPerRow: 1);

        $certificates = [];
        $pdfDocuments = [];

        if ($withCertificates) {
            $seller = $this->seller();

            // Ohne Eingabe: Eigentümer der Mappe = der Betrieb selbst
            $issuedFor = filled($issuedFor) ? $issuedFor : implode("\n", array_filter([
                $seller['name'],
                $seller['street'],
                trim((string) $seller['postal_code'].' '.(string) $seller['city']),
            ]));

            foreach ($this->reportWatches($includeConsignment) as $watch) {
                if ($watch->getAttribute('status') === WatchStatus::Consignment) {
                    continue;
                }

                $cert = $this->certificateData($watch, $issuedFor, $includePurchase, $maskSerial, $withDocuments);
                $certificates[] = $cert;

                // PDF-Belege aller Uhren sammeln — sie werden hinten angeheftet
                if ($withDocuments) {
                    $pdfDocuments = array_merge($pdfDocuments, $this->documentPdfs($watch, $cert['certNumber']));
                }
            }
        }

        $pdf = Pdf::loadView('pdf.inventory', [
            'report' => $report,
            'certificates' => $certificates,
            'generatedAt' => $report['generatedAt'],
            'seller' => $this->seller(),
        ])->output();

        return $this->appendPdfDocuments($pdf, $pdfDocuments);
    }

    /**
     * Bild-Belege (JPEG/PNG/WebP aus der Dokumente-Sammlung, z. B.
     * fotografierte Original-Kaufrechnungen) als Base64-JPEG für
     * eigene Anlage-Seiten hinter dem Zertifikat.
     *
     * @return array<int, array{data: string, name: string}>
     */
    private function documentImages(Watch $watch): array
    {
        $images = [];

        foreach ($watch->getMedia('documents') as $item) {
            if (! str_starts_with((string) $item->mime_type, 'image/')) {
                continue;
            }

            $data = $this->thumbBase64($item->getPath(), 1000);

            if ($data !== null) {
                $images[] = ['data' => $data, 'name' => (string) $item->file_name];
            }
        }

        return $images;
    }

    /**
     * PDF-Belege der Dokumente-Sammlung (Original-Kaufrechnungen,
     * Zertifikate) mit Zuordnungs-Label fürs Anheften per FPDI.
     *
     * @return array<int, array{path: string, label: string}>
     */
    private function documentPdfs(Watch $watch, string $certNumber): array
    {
        $pdfs = [];

        foreach ($watch->getMedia('documents') as $item) {
            if ($item->mime_type !== 'application/pdf' || ! is_file($item->getPath())) {
                continue;
            }

            $pdfs[] = [
                'path' => $item->getPath(),
                'label' => 'Anlage zu Zertifikat '.$certNumber.' — '.$watch->fullName().' — '.$item->file_name,
            ];
        }

        return $pdfs;
    }

    /**
     * PDF-Belege hinten an das dompdf-Dokument anheften (FPDI).
     * Jede erste Beleg-Seite bekommt oben ein kleines Zuordnungs-Label.
     * Nicht lesbare PDFs (Verschlüsselung, exotische Kompression)
     * werden geloggt und übersprungen — die Mappe kommt trotzdem raus.
     *
     * @param  array<int, array{path: string, label: string}>  $documents
     */
    private function appendPdfDocuments(string $pdf, array $documents): string
    {
        if ($documents === []) {
            return $pdf;
        }

        try {
            $merged = new Fpdi;

            $pages = $merged->setSourceFile(StreamReader::createByString($pdf));

            for ($page = 1; $page <= $pages; $page++) {
                $template = $merged->importPage($page);
                $size = $merged->getTemplateSize($template);
                $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merged->useTemplate($template);
            }
        } catch (Throwable $exception) {
            // Hauptdokument nicht parsebar → lieber ohne Anhänge ausliefern
            report($exception);

            return $pdf;
        }

        foreach ($documents as $document) {
            try {
                $pages = $merged->setSourceFile($document['path']);

                for ($page = 1; $page <= $pages; $page++) {
                    $template = $merged->importPage($page);
                    $size = $merged->getTemplateSize($template);
                    $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $merged->useTemplate($template);

                    if ($page === 1) {
                        // Zuordnungs-Label oben links (FPDF kann nur cp1252)
                        $merged->SetFont('Helvetica', '', 7);
                        $merged->SetTextColor(120, 120, 120);
                        $merged->SetXY(8, 4);
                        $merged->Cell(0, 4, (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $document['label']));
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $merged->Output('S');
    }

    /**
     * Alle Uhren des Versicherungs-Bestands (sortiert nach Marke/Modell).
     *
     * @return Collection<int, Watch>
     */
    private function reportWatches(bool $includeConsignment): Collection
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

        return Watch::query()
            ->whereIn('status', $statuses)
            ->with(['brand', 'media', 'serviceRecords', 'transactions'])
            ->get()
            ->sortBy(fn (Watch $watch): string => $watch->brand->name.' '.$watch->model_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->collect();
    }

    /**
     * Betriebsdaten für Kopf und Fußzeile der PDFs.
     *
     * @return array{name: string, street: mixed, postal_code: mixed, city: mixed, tax_number: mixed, vat_id: mixed}
     */
    private function seller(): array
    {
        return [
            'name' => (string) tenant('name'),
            'street' => tenant('company_street'),
            'postal_code' => tenant('company_postal_code'),
            'city' => tenant('company_city'),
            'tax_number' => tenant('tax_number'),
            'vat_id' => tenant('vat_id'),
        ];
    }

    /**
     * Seriennummer — optional teilgeschwärzt (erste 2 + letzte 2 Zeichen).
     */
    private function serial(?string $serial, bool $mask): ?string
    {
        if ($serial === null || ! $mask) {
            return $serial;
        }

        $length = mb_strlen($serial);

        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return mb_substr($serial, 0, 2).str_repeat('•', $length - 4).mb_substr($serial, -2);
    }

    /**
     * Wiederbeschaffungswert + Quelle (Marktwert → Angebotspreis → EK),
     * mit EK-Untergrenze: Marktwert unter Einkaufspreis → EK plus
     * Alterszuschlag (1. Jahr +10 %, 2. Jahr +15 %, ab 3. Jahr +20 %).
     *
     * @return array{0: float|null, 1: string|null}
     */
    private function replacementValue(Watch $watch): array
    {
        $purchase = $watch->purchase_price !== null ? (float) $watch->purchase_price : null;
        $market = $watch->current_market_value !== null ? (float) $watch->current_market_value : null;

        // Marktwert unter EK → EK + Alterszuschlag als Untergrenze
        if ($purchase !== null && $market !== null && $market < $purchase) {
            return $this->purchaseBasedValue($watch, $purchase);
        }

        if ($market !== null) {
            return [$market, 'Marktwert'];
        }

        if ($watch->asking_price !== null) {
            return [(float) $watch->asking_price, 'Angebotspreis'];
        }

        if ($purchase !== null) {
            return [$purchase, 'Einkaufspreis'];
        }

        return [null, null];
    }

    /**
     * Einkaufspreis + Alterszuschlag seit Kaufdatum (ersatzweise
     * Anlagedatum): 1. Jahr +10 %, 2. Jahr +15 %, ab dem 3. Jahr +20 %.
     *
     * @return array{0: float, 1: string}
     */
    private function purchaseBasedValue(Watch $watch, float $purchase): array
    {
        $reference = $watch->purchase_date ?? $watch->created_at;
        $years = $reference !== null ? (int) floor($reference->diffInYears(now())) : 0;

        [$surcharge, $label] = match (true) {
            $years <= 0 => [0.10, 'EK +10 % (1. Jahr)'],
            $years === 1 => [0.15, 'EK +15 % (2. Jahr)'],
            default => [0.20, 'EK +20 % (ab 3. Jahr)'],
        };

        return [round($purchase * (1 + $surcharge), 2), $label];
    }

    /**
     * Bis zu MAX_PHOTOS Fotos je Uhr als kleine JPEG-Thumbnails
     * (Base64) — Slot-Fotos zuerst (mit Perspektiven-Label), dann
     * weitere. dompdf wird mit Original-Fotos zu langsam/groß.
     *
     * @return array<int, array{data: string, label: string|null}>
     */
    private function photoThumbs(Watch $watch, int $limit = self::MAX_PHOTOS, int $width = 220): array
    {
        $media = $watch->getMedia('photos');

        // Slot-Fotos in Slot-Reihenfolge nach vorne
        $slotOrder = array_flip(array_column(PhotoSlot::cases(), 'value'));

        $sorted = $media->sortBy(function ($item) use ($slotOrder): int {
            $slot = $item->getCustomProperty('slot');

            return is_string($slot) && isset($slotOrder[$slot]) ? $slotOrder[$slot] : 100;
        })->take($limit);

        $thumbs = [];

        foreach ($sorted as $item) {
            $thumb = $this->thumbBase64($item->getPath(), $width);

            if ($thumb === null) {
                continue;
            }

            $slot = $item->getCustomProperty('slot');

            $thumbs[] = [
                'data' => $thumb,
                'label' => is_string($slot) ? PhotoSlot::tryFrom($slot)?->getLabel() : null,
            ];
        }

        return $thumbs;
    }

    /**
     * Kleines JPEG-Thumbnail (Base64) fürs PDF — Fehler liefern null.
     */
    private function thumbBase64(string $path, int $width = 220): ?string
    {
        try {
            if (! is_file($path)) {
                return null;
            }

            $image = @imagecreatefromstring((string) file_get_contents($path));

            if ($image === false) {
                return null;
            }

            // Nie hochskalieren — kleine Originale bleiben klein
            $thumb = imagescale($image, min($width, imagesx($image)));
            imagedestroy($image);

            if ($thumb === false) {
                return null;
            }

            ob_start();
            imagejpeg($thumb, null, 72);
            $jpeg = (string) ob_get_clean();
            imagedestroy($thumb);

            return $jpeg !== '' ? base64_encode($jpeg) : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
