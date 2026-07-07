<?php

/**
 * =========================================================================
 * WatchReferenceData — Ergebnis des KI-Referenz-Lookups (DTO)
 * =========================================================================
 *
 * Zweck:
 *   Typsicherer Container für die von der KI recherchierten Uhrendaten
 *   (WatchReferenceLookupService). Entkoppelt Service und Filament-Form:
 *   Das Formular liest nur dieses Objekt, nie die API-Antwort.
 *
 * Hinweis:
 *   Alle Felder sind nullable — die KI liefert nur, was sie sicher
 *   belegen kann. image_urls/source_urls werden in watches.research_data
 *   persistiert (Bild-Übernahme in die Media Library folgt in Modul 4).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\DataTransferObjects;

readonly class WatchReferenceData
{
    /**
     * @param  array<int, string>  $imageUrls
     * @param  array<int, string>  $sourceUrls
     */
    public function __construct(
        public ?string $brandName,
        public ?string $modelName,
        public ?string $caliberName,
        public ?int $productionYearFrom,
        public ?string $caseMaterial,
        public ?float $caseDiameterMm,
        public ?string $dialColor,
        public ?string $braceletMaterial,
        public ?string $description,
        public array $imageUrls = [],
        public array $sourceUrls = [],
    ) {}

    /**
     * Baut das DTO aus dem geparsten JSON der KI-Antwort.
     * Defensive Konvertierung — die Struktur ist promptseitig definiert,
     * aber nicht API-seitig garantiert (kein strukturiertes Output-Format
     * möglich, da Web-Suche Citations erzeugt).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = fn (string $key): ?string => is_string($data[$key] ?? null) && trim((string) $data[$key]) !== ''
            ? trim((string) $data[$key])
            : null;

        $stringList = fn (string $key): array => array_values(array_filter(
            is_array($data[$key] ?? null) ? $data[$key] : [],
            fn ($url): bool => is_string($url) && str_starts_with($url, 'http'),
        ));

        return new self(
            brandName: $string('brand_name'),
            modelName: $string('model_name'),
            caliberName: $string('caliber_name'),
            productionYearFrom: is_numeric($data['production_year_from'] ?? null)
                ? (int) $data['production_year_from']
                : null,
            caseMaterial: $string('case_material'),
            caseDiameterMm: is_numeric($data['case_diameter_mm'] ?? null)
                ? (float) $data['case_diameter_mm']
                : null,
            dialColor: $string('dial_color'),
            braceletMaterial: $string('bracelet_material'),
            description: $string('description'),
            imageUrls: $stringList('image_urls'),
            sourceUrls: $stringList('source_urls'),
        );
    }

    /**
     * Persistierbare Form für watches.research_data (JSON-Spalte).
     *
     * @return array<string, mixed>
     */
    public function toResearchData(): array
    {
        return [
            'description' => $this->description,
            'image_urls' => $this->imageUrls,
            'source_urls' => $this->sourceUrls,
            'looked_up_at' => now()->toIso8601String(),
        ];
    }
}
