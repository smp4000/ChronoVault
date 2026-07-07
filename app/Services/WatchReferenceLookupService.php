<?php

/**
 * =========================================================================
 * WatchReferenceLookupService — KI-Recherche zu Uhren-Referenznummern
 * =========================================================================
 *
 * Zweck:
 *   Recherchiert zu einer Referenznummer (z. B. "126610LN") die Uhrendaten
 *   über die Anthropic Claude API mit Web-Suche und liefert ein
 *   WatchReferenceData-DTO. Das Filament-Formular (WatchForm) befüllt
 *   damit die Felder und speichert Bild-/Quellen-URLs in
 *   watches.research_data (Bild-Download folgt in Modul 4).
 *
 * Verantwortlichkeiten:
 *   - Messages-API-Aufruf (claude-opus-4-8 + web_search-Server-Tool)
 *   - Robustes JSON-Parsing der Antwort (parseResponseJson)
 *   - Matching der KI-Markennamen gegen die Tenant-Stammdaten
 *     (resolveBrand/resolveCaliber) — es werden NIE automatisch neue
 *     Stammdaten angelegt (Berechtigungs- und Qualitätsfrage)
 *
 * WARUM kein strukturiertes Output-Format (output_config.format):
 *   Die Web-Suche erzeugt Citations, die mit Structured Outputs
 *   inkompatibel sind. Stattdessen: striktes JSON per Prompt + defensives
 *   Parsing (Markdown-Fences, umgebender Text).
 *
 * Konfiguration:
 *   services.anthropic.api_key (ANTHROPIC_API_KEY). Ohne Key wirft
 *   lookup() eine RuntimeException mit deutscher Meldung — die UI zeigt
 *   sie als Notification.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use App\DataTransferObjects\WatchReferenceData;
use App\Enums\BraceletMaterial;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\WatchColor;
use App\Enums\WatchFunction;
use App\Enums\WatchGender;
use App\Models\Brand;
use App\Models\Caliber;
use RuntimeException;

class WatchReferenceLookupService
{
    /**
     * Opus als bewusste Qualitätsentscheidung: Referenz-Recherche lebt von
     * Faktenwissen + Web-Suche-Synthese; Fehler hier erzeugen falsche
     * Bestandsdaten. Pro Lookup fallen nur wenige Cent an.
     */
    private const MODEL = 'claude-opus-4-8';

    private const MAX_TOKENS = 4096;

    /** Server-Tool-Schleife (pause_turn) höchstens so oft fortsetzen. */
    private const MAX_CONTINUATIONS = 3;

    public function __construct(private ?Client $client = null) {}

    /**
     * Recherchiert die Referenznummer und liefert die Uhrendaten.
     *
     * @throws RuntimeException wenn kein API-Key konfiguriert ist oder
     *                          die Antwort nicht auswertbar ist
     */
    public function lookup(string $referenceNumber, ?string $brandHint = null): WatchReferenceData
    {
        $client = $this->client ?? $this->makeClient();

        // Web-Recherche + Antwortsynthese können deutlich länger dauern
        // als das PHP-Standard-Limit (30 s unter XAMPP).
        set_time_limit(180);

        $messages = [[
            'role' => 'user',
            'content' => $this->buildPrompt($referenceNumber, $brandHint),
        ]];

        $response = $client->messages->create(
            maxTokens: self::MAX_TOKENS,
            messages: $messages,
            model: self::MODEL,
            tools: [
                ['type' => 'web_search_20260209', 'name' => 'web_search'],
            ],
        );

        // Server-Tool-Schleife: pause_turn heißt "weitermachen" — Assistant-
        // Turn anhängen und erneut senden, die API setzt selbst fort.
        $continuations = 0;
        while ($response->stopReason === 'pause_turn' && $continuations < self::MAX_CONTINUATIONS) {
            $messages[] = ['role' => 'assistant', 'content' => $response->content];

            $response = $client->messages->create(
                maxTokens: self::MAX_TOKENS,
                messages: $messages,
                model: self::MODEL,
                tools: [
                    ['type' => 'web_search_20260209', 'name' => 'web_search'],
                ],
            );

            $continuations++;
        }

        if ($response->stopReason === 'refusal') {
            throw new RuntimeException('Die KI hat die Anfrage abgelehnt. Bitte Referenznummer prüfen.');
        }

        // Gesamten Text einsammeln (Web-Suche kann mehrere Textblöcke liefern).
        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return WatchReferenceData::fromArray(self::parseResponseJson($text));
    }

    /**
     * Extrahiert das JSON-Objekt aus der KI-Antwort — tolerant gegenüber
     * Markdown-Fences und umgebendem Text. Public static, damit das
     * Parsing ohne API-Aufruf testbar ist.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException wenn kein auswertbares JSON enthalten ist
     */
    public static function parseResponseJson(string $text): array
    {
        // 1. Direktversuch (idealerweise ist die Antwort pures JSON).
        $decoded = json_decode(trim($text), true);

        // 2. Fallback: erstes '{' bis letztes '}' (Fences/Begleittext entfernen).
        if (! is_array($decoded)) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Die KI-Antwort enthielt kein auswertbares JSON. Bitte erneut versuchen.');
        }

        return $decoded;
    }

    /**
     * Ordnet den KI-Markennamen einer Stammdaten-Marke zu
     * (case-insensitiv, exakter Name). Kein Fuzzy-Matching — lieber
     * kein Treffer als eine falsche Marke am Datensatz.
     */
    public function resolveBrand(?string $brandName): ?Brand
    {
        if ($brandName === null) {
            return null;
        }

        return Brand::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($brandName)])
            ->first();
    }

    /**
     * Ordnet den KI-Kalibernamen einem Kaliber der Marke zu. Toleriert
     * Präfix-Varianten wie "Kaliber 3235" vs. "3235" in beide Richtungen.
     */
    public function resolveCaliber(?Brand $brand, ?string $caliberName): ?Caliber
    {
        if ($brand === null || $caliberName === null) {
            return null;
        }

        $needle = mb_strtolower(trim($caliberName));

        return $brand->calibers()
            ->get()
            ->first(function (Caliber $caliber) use ($needle): bool {
                $name = mb_strtolower($caliber->name);

                return $name === $needle
                    || str_contains($needle, $name)
                    || str_contains($name, $needle);
            });
    }

    private function makeClient(): Client
    {
        $apiKey = config('services.anthropic.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException(
                'KI-Lookup ist nicht konfiguriert: ANTHROPIC_API_KEY in der .env hinterlegen.'
            );
        }

        return new Client(apiKey: $apiKey);
    }

    private function buildPrompt(string $referenceNumber, ?string $brandHint): string
    {
        $hint = $brandHint !== null ? " Der Hersteller ist vermutlich: {$brandHint}." : '';

        // Erlaubte Enum-Codes direkt aus den Enums — Prompt und Datenmodell
        // können nicht auseinanderlaufen.
        $movements = implode('|', array_column(MovementType::cases(), 'value'));
        $genders = implode('|', array_column(WatchGender::cases(), 'value'));
        $materials = implode('|', array_column(CaseMaterial::cases(), 'value'));
        $colors = implode('|', array_column(WatchColor::cases(), 'value'));
        $glasses = implode('|', array_column(GlassType::cases(), 'value'));
        $numerals = implode('|', array_column(DialNumerals::cases(), 'value'));
        $braceletMaterials = implode('|', array_column(BraceletMaterial::cases(), 'value'));
        $clasps = implode('|', array_column(ClaspType::cases(), 'value'));
        $functions = implode('|', array_column(WatchFunction::cases(), 'value'));

        return <<<PROMPT
            Du bist ein Uhren-Experte für einen Fachhändler. Recherchiere die Armbanduhr mit der Referenznummer "{$referenceNumber}".{$hint}

            Nutze die Web-Suche, um die Angaben zu verifizieren, und sammle 2-4 URLs zu hochwertigen Produktfotos (direkte Bild-URLs oder Produktseiten des Herstellers bzw. seriöser Händler).

            Antworte AUSSCHLIESSLICH mit einem JSON-Objekt in exakt dieser Struktur, ohne Markdown-Zäune und ohne Begleittext. Verwende null für alles, was du nicht sicher belegen kannst. Felder mit Wertelisten (a|b|c) dürfen NUR einen dieser Codes oder null enthalten:

            {
              "brand_name": "Markenname oder null",
              "model_name": "Modellname ohne Marke, z. B. Submariner Date, oder null",
              "caliber_name": "Kaliberbezeichnung ohne Markenname, z. B. 3235, oder null",
              "movement_type": "{$movements}",
              "production_year_from": Produktionsbeginn als Jahreszahl oder null,
              "gender": "{$genders}",
              "case_material": "{$materials}",
              "case_diameter_mm": Breite in mm als Zahl oder null,
              "case_height_mm": zweite Gehäusedimension in mm bei nicht-runden Gehäusen, sonst null,
              "glass_type": "{$glasses}",
              "bezel_material": "{$materials}",
              "bezel_color": "{$colors}",
              "water_resistance_bar": Wasserdichtigkeit in bar als ganze Zahl oder null,
              "dial_color": "{$colors}",
              "dial_numerals": "{$numerals}",
              "bracelet_material": "{$braceletMaterials}",
              "bracelet_color": "{$colors}",
              "clasp_type": "{$clasps}",
              "clasp_material": "{$materials}",
              "lug_width_mm": Bandanstoßbreite in mm als ganze Zahl oder null,
              "functions": Array mit zutreffenden Codes aus {$functions} — leer lassen, wenn unbekannt,
              "description": "2-3 Sätze Kurzbeschreibung der Uhr auf Deutsch oder null",
              "image_urls": ["https://...", "..."],
              "source_urls": ["https://...", "..."]
            }
            PROMPT;
    }
}
