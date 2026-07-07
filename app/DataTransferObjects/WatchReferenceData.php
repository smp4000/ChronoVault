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
 * Enum-Felder:
 *   Die KI liefert Enum-CODES (steel, black, sapphire …) — der Prompt
 *   listet die erlaubten Werte auf. Unbekannte Codes werden hier per
 *   tryFrom() verworfen (null) statt zu crashen.
 *
 * Hinweis:
 *   Alle Felder sind nullable — die KI liefert nur, was sie sicher
 *   belegen kann. image_urls/source_urls werden in watches.research_data
 *   persistiert (Bild-Übernahme in die Media Library folgt in Modul 4).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\BraceletMaterial;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\WatchColor;
use App\Enums\WatchGender;

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
        public ?MovementType $movementType,
        public ?int $productionYearFrom,
        public ?WatchGender $gender,
        public ?CaseMaterial $caseMaterial,
        public ?float $caseDiameterMm,
        public ?float $caseHeightMm,
        public ?GlassType $glassType,
        public ?CaseMaterial $bezelMaterial,
        public ?WatchColor $bezelColor,
        public ?int $waterResistanceBar,
        public ?WatchColor $dialColor,
        public ?DialNumerals $dialNumerals,
        public ?BraceletMaterial $braceletMaterial,
        public ?WatchColor $braceletColor,
        public ?ClaspType $claspType,
        public ?CaseMaterial $claspMaterial,
        public ?int $lugWidthMm,
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

        $int = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;
        $float = fn (string $key): ?float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : null;

        /**
         * Enum-Code auflösen — unbekannte Codes werden verworfen.
         *
         * @template T
         *
         * @param  class-string<T>  $enum
         */
        $enum = fn (string $enum, string $key): mixed => $string($key) !== null
            ? $enum::tryFrom(mb_strtolower((string) $string($key)))
            : null;

        $stringList = fn (string $key): array => array_values(array_filter(
            is_array($data[$key] ?? null) ? $data[$key] : [],
            fn ($url): bool => is_string($url) && str_starts_with($url, 'http'),
        ));

        return new self(
            brandName: $string('brand_name'),
            modelName: $string('model_name'),
            caliberName: $string('caliber_name'),
            movementType: $enum(MovementType::class, 'movement_type'),
            productionYearFrom: $int('production_year_from'),
            gender: $enum(WatchGender::class, 'gender'),
            caseMaterial: $enum(CaseMaterial::class, 'case_material'),
            caseDiameterMm: $float('case_diameter_mm'),
            caseHeightMm: $float('case_height_mm'),
            glassType: $enum(GlassType::class, 'glass_type'),
            bezelMaterial: $enum(CaseMaterial::class, 'bezel_material'),
            bezelColor: $enum(WatchColor::class, 'bezel_color'),
            waterResistanceBar: $int('water_resistance_bar'),
            dialColor: $enum(WatchColor::class, 'dial_color'),
            dialNumerals: $enum(DialNumerals::class, 'dial_numerals'),
            braceletMaterial: $enum(BraceletMaterial::class, 'bracelet_material'),
            braceletColor: $enum(WatchColor::class, 'bracelet_color'),
            claspType: $enum(ClaspType::class, 'clasp_type'),
            claspMaterial: $enum(CaseMaterial::class, 'clasp_material'),
            lugWidthMm: $int('lug_width_mm'),
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
