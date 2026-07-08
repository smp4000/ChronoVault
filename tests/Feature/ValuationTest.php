<?php

/**
 * =========================================================================
 * ValuationTest — Bewertungen & Marktwert (Modul 7)
 * =========================================================================
 *
 * Abgedeckt:
 *   - RecordValuationAction: Historie + Schnellzugriff-Sync; ältere
 *     (nachgetragene) Bewertungen überschreiben den aktuellen Wert nicht
 *   - MarketValueLookupService via Http::fake (JSON + Citations-Merge)
 *   - Fehlerfälle (kein Key, kein Wert in der Antwort)
 *   - Berechtigungen (valuations.*) je Rolle
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Valuations\RecordValuationAction;
use App\Enums\UserRole;
use App\Enums\ValuationSource;
use App\Models\Brand;
use App\Models\User;
use App\Models\Watch;
use App\Services\MarketValueLookupService;
use Illuminate\Support\Facades\Http;

it('records valuations and keeps the watch snapshot in sync', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            $action = app(RecordValuationAction::class);

            $action->execute($watch, [
                'source' => ValuationSource::Manual->value,
                'market_value' => 15000,
                'valued_at' => '2026-07-01',
            ]);

            expect($watch->refresh()->current_market_value)->toBe('15000.00')
                ->and($watch->last_valuation_at->toDateString())->toBe('2026-07-01');

            // Neuere Bewertung aktualisiert den Schnellzugriff …
            $action->execute($watch, [
                'source' => ValuationSource::AiResearch->value,
                'market_value' => 15500,
                'valued_at' => '2026-07-08',
            ]);

            expect($watch->refresh()->current_market_value)->toBe('15500.00');

            // … eine NACHGETRAGENE ältere Bewertung nicht.
            $action->execute($watch, [
                'source' => ValuationSource::Manual->value,
                'market_value' => 12000,
                'valued_at' => '2025-01-01',
            ]);

            expect($watch->refresh()->current_market_value)->toBe('15500.00')
                ->and($watch->valuations()->count())->toBe(3);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('looks up the market value via perplexity and merges citations', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            config()->set('services.perplexity.api_key', 'pplx-test');

            Http::fake([
                'api.perplexity.ai/*' => Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'market_value_eur' => 14800,
                                'value_low_eur' => 13900,
                                'value_high_eur' => 15600,
                                'summary' => 'Stabile Nachfrage, leichte Aufwärtstendenz.',
                                'source_urls' => ['https://www.chrono24.de/x'],
                            ]),
                        ],
                    ]],
                    'citations' => ['https://www.chrono24.de/x', 'https://watchcharts.com/y'],
                ]),
            ]);

            $watch = Watch::factory()->fullSet()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Submariner Date',
                'reference_number' => '126610LN',
            ]);

            $data = app(MarketValueLookupService::class)->lookup($watch);

            expect($data->marketValue)->toBe(14800.0)
                ->and($data->valueLow)->toBe(13900.0)
                ->and($data->valueHigh)->toBe(15600.0)
                ->and($data->summary)->toContain('Aufwärtstendenz')
                ->and($data->sourceUrls)->toBe(['https://www.chrono24.de/x', 'https://watchcharts.com/y']);

            // Der Prompt enthält Zustand & Lieferumfang
            Http::assertSent(fn ($request): bool => str_contains((string) $request->body(), 'Full Set')
                && str_contains((string) $request->body(), '126610LN'));
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('fails with clear german messages on missing key or missing value', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            // Kein Key
            config()->set('services.perplexity.api_key', null);
            expect(fn () => app(MarketValueLookupService::class)->lookup($watch))
                ->toThrow(RuntimeException::class, 'PERPLEXITY_API_KEY');

            // Antwort ohne Wert
            config()->set('services.perplexity.api_key', 'pplx-test');
            Http::fake([
                'api.perplexity.ai/*' => Http::response([
                    'choices' => [['message' => ['content' => '{"market_value_eur": null}']]],
                ]),
            ]);

            expect(fn () => app(MarketValueLookupService::class)->lookup($watch))
                ->toThrow(RuntimeException::class, 'keinen Marktwert');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('grants valuation permissions according to role semantics', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $employee = User::factory()->create();
            $employee->assignRole(UserRole::Employee->value);

            $viewer = User::factory()->create();
            $viewer->assignRole(UserRole::Viewer->value);

            expect($employee->can('valuations.create'))->toBeTrue()
                ->and($employee->can('valuations.delete'))->toBeFalse()
                ->and($viewer->can('valuations.view'))->toBeTrue()
                ->and($viewer->can('valuations.create'))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});
