<?php

/**
 * =========================================================================
 * MarketValueLookupService — KI-Recherche des aktuellen Marktwerts
 * =========================================================================
 *
 * Zweck:
 *   Recherchiert über Perplexity (sonar-pro, Web-Suche eingebaut) den
 *   aktuellen Gebrauchtmarkt-Preis einer Uhr — unter Berücksichtigung
 *   von Zustand und Lieferumfang (Box/Papiere) — und liefert ein
 *   MarketValueData-DTO (Wert, Spanne, Quellen).
 *
 * Abgrenzung:
 *   Kein Anthropic-Fallback wie beim Referenz-Lookup — Marktpreise
 *   brauchen zwingend aktuelle Web-Daten; ohne PERPLEXITY_API_KEY
 *   gibt es eine klare deutsche Fehlermeldung.
 *
 * WICHTIG: Die Einschätzung ist eine RECHERCHE-Grundlage, kein
 * Gutachten — die Quellen (citations) werden mitgeliefert.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MarketValueData;
use App\Enums\WatchCondition;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MarketValueLookupService
{
    /**
     * Recherchiert den aktuellen Marktwert der Uhr.
     *
     * @throws RuntimeException wenn kein API-Key konfiguriert ist oder
     *                          die Antwort nicht auswertbar ist
     */
    public function lookup(Watch $watch): MarketValueData
    {
        $apiKey = config('services.perplexity.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException(
                'Marktwert-Recherche ist nicht konfiguriert: PERPLEXITY_API_KEY in der .env hinterlegen.'
            );
        }

        // Web-Recherche kann das PHP-Limit (30 s unter XAMPP) überschreiten.
        set_time_limit(120);

        $response = Http::withToken($apiKey)
            ->timeout(100)
            ->post('https://api.perplexity.ai/chat/completions', [
                'model' => (string) config('services.perplexity.model', 'sonar-pro'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein Experte für den Gebrauchtmarkt von Luxusuhren. Antworte ausschließlich mit dem angeforderten JSON-Objekt — ohne Markdown-Zäune, ohne Begleittext.',
                    ],
                    ['role' => 'user', 'content' => $this->buildPrompt($watch)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Perplexity-Anfrage fehlgeschlagen (HTTP '.$response->status().'). API-Key und Guthaben prüfen.'
            );
        }

        $data = WatchReferenceLookupService::parseResponseJson(
            (string) $response->json('choices.0.message.content', '')
        );

        // Tatsächlich genutzte Quellen (citations) ergänzen.
        $citations = array_values(array_filter(
            (array) $response->json('citations', []),
            fn ($url): bool => is_string($url) && str_starts_with($url, 'http'),
        ));

        $data['source_urls'] = array_values(array_unique(array_merge(
            is_array($data['source_urls'] ?? null) ? $data['source_urls'] : [],
            $citations,
        )));

        return MarketValueData::fromArray($data);
    }

    private function buildPrompt(Watch $watch): string
    {
        // getAttribute + instanceof: Enum-Cast typsicher für statische Analyse.
        $conditionAttribute = $watch->getAttribute('condition');
        $condition = $conditionAttribute instanceof WatchCondition
            ? $conditionAttribute->getLabel()
            : 'unbekannt';
        $scope = match (true) {
            (bool) $watch->has_box && (bool) $watch->has_papers => 'Full Set (Box und Papiere)',
            (bool) $watch->has_papers => 'mit Papieren, ohne Box',
            (bool) $watch->has_box => 'mit Box, ohne Papiere',
            default => 'ohne Box und Papiere',
        };
        $year = $watch->production_year !== null ? " Baujahr ca. {$watch->production_year}." : '';

        return <<<PROMPT
            Recherchiere den AKTUELLEN Gebrauchtmarkt-Preis (EUR, Händler-Verkaufspreise auf Plattformen wie Chrono24) für diese Uhr:

            {$watch->fullName()} — Zustand: {$condition}, Lieferumfang: {$scope}.{$year}

            Berücksichtige Zustand und Lieferumfang bei der Einschätzung. Antworte AUSSCHLIESSLICH mit einem JSON-Objekt in exakt dieser Struktur (Zahlen ohne Tausendertrennzeichen, null wenn nicht ermittelbar):

            {
              "market_value_eur": realistischer Marktwert als Zahl,
              "value_low_eur": unteres Ende der Preisspanne oder null,
              "value_high_eur": oberes Ende der Preisspanne oder null,
              "summary": "2-3 Sätze zur Markteinschätzung auf Deutsch",
              "source_urls": ["https://...", "..."]
            }
            PROMPT;
    }
}
