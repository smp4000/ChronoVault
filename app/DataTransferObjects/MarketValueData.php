<?php

/**
 * =========================================================================
 * MarketValueData — Ergebnis der KI-Marktwert-Recherche (DTO)
 * =========================================================================
 *
 * Zweck:
 *   Typsicherer Container für die recherchierte Marktpreis-Einschätzung
 *   (MarketValueLookupService). marketValue ist der Pflichtwert;
 *   Spanne, Zusammenfassung und Quellen sind optional.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\DataTransferObjects;

readonly class MarketValueData
{
    /**
     * @param  array<int, string>  $sourceUrls
     */
    public function __construct(
        public float $marketValue,
        public ?float $valueLow,
        public ?float $valueHigh,
        public ?string $summary,
        public array $sourceUrls = [],
    ) {}

    /**
     * Baut das DTO aus dem geparsten JSON der KI-Antwort — defensiv,
     * wirft bei fehlendem Hauptwert.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException wenn kein Marktwert enthalten ist
     */
    public static function fromArray(array $data): self
    {
        if (! is_numeric($data['market_value_eur'] ?? null)) {
            throw new \RuntimeException('Die KI konnte keinen Marktwert ermitteln. Bitte Referenz/Angaben prüfen.');
        }

        $float = fn (string $key): ?float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : null;

        return new self(
            marketValue: (float) $data['market_value_eur'],
            valueLow: $float('value_low_eur'),
            valueHigh: $float('value_high_eur'),
            summary: is_string($data['summary'] ?? null) && trim((string) $data['summary']) !== ''
                ? trim((string) $data['summary'])
                : null,
            sourceUrls: array_values(array_filter(
                is_array($data['source_urls'] ?? null) ? $data['source_urls'] : [],
                fn ($url): bool => is_string($url) && str_starts_with($url, 'http'),
            )),
        );
    }
}
