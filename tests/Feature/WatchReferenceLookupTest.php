<?php

/**
 * =========================================================================
 * WatchReferenceLookupTest — Tests des KI-Referenz-Lookups (Modul 3)
 * =========================================================================
 *
 * Abgedeckt (OHNE echte API-Aufrufe — kein Netzwerk in Tests):
 *   - JSON-Parsing der KI-Antwort (pur, mit Fences, mit Begleittext, kaputt)
 *   - DTO-Konvertierung inkl. defensiver Typbehandlung
 *   - Brand-/Caliber-Matching gegen die Tenant-Stammdaten
 *   - Fehlermeldung bei fehlendem API-Key
 * =========================================================================
 */

declare(strict_types=1);

use App\DataTransferObjects\WatchReferenceData;
use App\Enums\BraceletMaterial;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\WatchColor;
use App\Enums\WatchGender;
use App\Models\Brand;
use App\Services\WatchReferenceLookupService;
use Illuminate\Support\Facades\Http;

it('parses pure json, fenced json and json with surrounding text', function () {
    $pure = '{"brand_name": "Rolex"}';
    $fenced = "```json\n{\"brand_name\": \"Rolex\"}\n```";
    $chatty = "Hier ist das Ergebnis:\n{\"brand_name\": \"Rolex\"}\nViel Erfolg!";

    expect(WatchReferenceLookupService::parseResponseJson($pure))->toBe(['brand_name' => 'Rolex'])
        ->and(WatchReferenceLookupService::parseResponseJson($fenced))->toBe(['brand_name' => 'Rolex'])
        ->and(WatchReferenceLookupService::parseResponseJson($chatty))->toBe(['brand_name' => 'Rolex']);
});

it('throws a german error when the response contains no json', function () {
    WatchReferenceLookupService::parseResponseJson('Leider konnte ich nichts finden.');
})->throws(RuntimeException::class, 'kein auswertbares JSON');

it('builds the dto defensively from partial data', function () {
    $data = WatchReferenceData::fromArray([
        'brand_name' => '  Rolex  ',
        'model_name' => 'Submariner Date',
        'caliber_name' => null,
        'production_year_from' => '2020',
        'case_diameter_mm' => 41,
        'dial_color' => '',
        'image_urls' => ['https://example.com/a.jpg', 'kein-link', 42],
        'unexpected_key' => 'wird ignoriert',
    ]);

    expect($data->brandName)->toBe('Rolex')
        ->and($data->productionYearFrom)->toBe(2020)
        ->and($data->caseDiameterMm)->toBe(41.0)
        ->and($data->dialColor)->toBeNull()
        ->and($data->braceletMaterial)->toBeNull()
        ->and($data->imageUrls)->toBe(['https://example.com/a.jpg'])
        ->and($data->toResearchData())->toHaveKeys(['description', 'image_urls', 'source_urls', 'looked_up_at']);
});

it('maps enum codes from the ai response and discards unknown codes', function () {
    $data = WatchReferenceData::fromArray([
        'movement_type' => 'automatic',
        'gender' => 'MENS', // Groß-/Kleinschreibung tolerieren
        'case_material' => 'steel',
        'glass_type' => 'sapphire',
        'bezel_color' => 'black',
        'dial_color' => 'blue',
        'dial_numerals' => 'indices',
        'bracelet_material' => 'rubber',
        'clasp_type' => 'folding_clasp',
        'water_resistance_bar' => '20',
        'lug_width_mm' => 21,
        'functions' => ['chronograph', 'date', 'quantensprung'], // unbekannte Codes fliegen raus
        // Unbekannte Codes dürfen nicht crashen, sondern werden verworfen
        'bracelet_color' => 'regenbogen',
        'clasp_material' => 'unobtainium',
    ]);

    expect($data->movementType)->toBe(MovementType::Automatic)
        ->and($data->gender)->toBe(WatchGender::Mens)
        ->and($data->caseMaterial)->toBe(CaseMaterial::Steel)
        ->and($data->glassType)->toBe(GlassType::Sapphire)
        ->and($data->bezelColor)->toBe(WatchColor::Black)
        ->and($data->dialColor)->toBe(WatchColor::Blue)
        ->and($data->dialNumerals)->toBe(DialNumerals::Indices)
        ->and($data->braceletMaterial)->toBe(BraceletMaterial::Rubber)
        ->and($data->claspType)->toBe(ClaspType::FoldingClasp)
        ->and($data->waterResistanceBar)->toBe(20)
        ->and($data->lugWidthMm)->toBe(21)
        ->and($data->functions)->toBe(['chronograph', 'date'])
        ->and($data->braceletColor)->toBeNull()
        ->and($data->claspMaterial)->toBeNull();
});

it('resolves brands and calibers against tenant master data', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $service = new WatchReferenceLookupService;

            // Marke: case-insensitiv, exakt — kein Fuzzy-Matching
            $rolex = $service->resolveBrand('rolex');
            expect($rolex)->not->toBeNull()
                ->and($rolex->name)->toBe('Rolex')
                ->and($service->resolveBrand('Rolexx'))->toBeNull()
                ->and($service->resolveBrand(null))->toBeNull();

            // Kaliber: toleriert Präfix-Varianten ("Kaliber 3235" ↔ "3235")
            expect($service->resolveCaliber($rolex, '3235')?->name)->toBe('3235')
                ->and($service->resolveCaliber($rolex, 'Kaliber 3235')?->name)->toBe('3235')
                ->and($service->resolveCaliber($rolex, '9999'))->toBeNull()
                ->and($service->resolveCaliber(null, '3235'))->toBeNull();

            // Kaliber einer anderen Marke wird nicht gefunden
            $omega = Brand::where('name', 'Omega')->firstOrFail();
            expect($service->resolveCaliber($omega, '3235'))->toBeNull();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('fails with a helpful message when no api key is configured', function () {
    config()->set('services.perplexity.api_key', null);
    config()->set('services.anthropic.api_key', null);

    (new WatchReferenceLookupService)->lookup('126610LN');
})->throws(RuntimeException::class, 'PERPLEXITY_API_KEY');

it('looks up watch data via perplexity and merges citations', function () {
    config()->set('services.perplexity.api_key', 'pplx-test');

    Http::fake([
        'api.perplexity.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'brand_name' => 'TAG Heuer',
                        'model_name' => 'Formula 1 Chronograph x Gulf',
                        'movement_type' => 'quartz',
                        'case_material' => 'steel',
                        'case_diameter_mm' => 43,
                        'functions' => ['chronograph', 'date'],
                        'description' => 'Sondermodell in Gulf-Lackierung.',
                        'image_urls' => ['https://example.com/gulf.jpg'],
                        'source_urls' => ['https://www.tagheuer.com/x'],
                    ]),
                ],
            ]],
            'citations' => ['https://www.chrono24.de/y', 'https://www.tagheuer.com/x'],
            'images' => [
                ['image_url' => 'https://haendler.example.com/echt-1.jpg', 'origin_url' => 'https://haendler.example.com/produkt'],
                ['image_url' => 'https://haendler.example.com/echt-2.jpg'],
            ],
        ]),
    ]);

    $data = (new WatchReferenceLookupService)->lookup('CBZ208B.BF0009');

    expect($data->brandName)->toBe('TAG Heuer')
        ->and($data->movementType)->toBe(MovementType::Quartz)
        ->and($data->caseMaterial)->toBe(CaseMaterial::Steel)
        ->and($data->functions)->toBe(['chronograph', 'date'])
        // Echte Such-Bilder VOR den vom Modell genannten URLs
        ->and($data->imageUrls)->toBe([
            'https://haendler.example.com/echt-1.jpg',
            'https://haendler.example.com/echt-2.jpg',
            'https://example.com/gulf.jpg',
        ])
        // citations werden dedupliziert in die Quellen gemerged
        ->and($data->sourceUrls)->toBe(['https://www.tagheuer.com/x', 'https://www.chrono24.de/y']);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'api.perplexity.ai')
            && $request->hasHeader('Authorization', 'Bearer pplx-test')
            && str_contains((string) $request->body(), 'CBZ208B.BF0009');
    });
});

it('reports perplexity api errors with a german message', function () {
    config()->set('services.perplexity.api_key', 'pplx-test');

    Http::fake([
        'api.perplexity.ai/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    (new WatchReferenceLookupService)->lookup('126610LN');
})->throws(RuntimeException::class, 'Perplexity-Anfrage fehlgeschlagen (HTTP 401)');
