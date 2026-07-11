<?php

/**
 * =========================================================================
 * WatchWishlistTest — Wunschliste als Uhren-Status (Zielpreis-Alarm)
 * =========================================================================
 *
 * Abgedeckt:
 *   - RecordValuationAction löst bei Wunschlisten-Uhren den Alarm aus:
 *     Ziel erreicht → GENAU EINE Mail; über Ziel → Re-Arm; erneut
 *     darunter → neue Mail
 *   - Wunschlisten-Uhren sind NICHT im Shop und NICHT im Bestandswert
 *     (Versicherungsliste)
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Valuations\RecordValuationAction;
use App\Enums\ValuationSource;
use App\Enums\WatchStatus;
use App\Mail\WishlistPriceAlertMail;
use App\Models\Brand;
use App\Models\Watch;
use App\Services\InventoryReportService;
use Illuminate\Support\Facades\Mail;

it('alerts once when a wishlist watch reaches its target price', function () {
    $tenant = provisionTenant();

    $tenant->update(['notification_email' => 'verkauf@example.test']);

    try {
        $tenant->run(function () {
            Mail::fake();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Explorer Wunsch',
                'reference_number' => '124270',
                'status' => WatchStatus::Wishlist,
                'wishlist_target_price' => 4500,
            ]);

            $record = app(RecordValuationAction::class);

            $valuate = fn (float $value) => $record->execute($watch->refresh(), [
                'source' => ValuationSource::AiResearch,
                'market_value' => $value,
                'value_low' => $value - 200,
                'value_high' => $value + 300,
                'valued_at' => now(),
                'summary' => 'Guter Einstiegszeitpunkt.',
            ]);

            // 1. Unter Ziel → Alarm-Mail
            $valuate(4400);

            expect($watch->refresh()->wishlistTargetReached())->toBeTrue()
                ->and($watch->getAttribute('wishlist_notified_at'))->not->toBeNull();

            Mail::assertSent(WishlistPriceAlertMail::class, 1);
            Mail::assertSent(WishlistPriceAlertMail::class, function (WishlistPriceAlertMail $mail): bool {
                $mail->assertTo('verkauf@example.test');

                $html = $mail->render();

                return str_contains($html, 'Zielpreis erreicht')
                    && str_contains($html, 'Explorer Wunsch')
                    && str_contains($html, '4.400')
                    && str_contains($html, '4.500')
                    && str_contains($html, 'Guter Einstiegszeitpunkt.');
            });

            // 2. Weiter unter Ziel → KEINE zweite Mail
            $valuate(4350);
            Mail::assertSent(WishlistPriceAlertMail::class, 1);

            // 3. Über Ziel → Re-Arm
            $valuate(4800);
            expect($watch->refresh()->getAttribute('wishlist_notified_at'))->toBeNull();

            // 4. Erneut unter Ziel → zweite Mail
            $valuate(4200);
            Mail::assertSent(WishlistPriceAlertMail::class, 2);

            // Wunschliste bleibt außen vor: kein Shop, kein Bestandswert
            expect(Watch::query()->visibleInShop()->count())->toBe(0)
                ->and(app(InventoryReportService::class)->data(includeConsignment: true)['count'])->toBe(0);
        });
    } finally {
        destroyTenant($tenant);
    }
});
