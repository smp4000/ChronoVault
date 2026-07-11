<?php

/**
 * =========================================================================
 * WishlistTest — Wunschliste: Bewertung + Zielpreis-Alarm
 * =========================================================================
 *
 * Abgedeckt (Perplexity via Http::fake):
 *   - ValuateWishlistItemAction pflegt Marktwert/Spanne/Zeitstempel
 *   - Zielpreis erreicht → GENAU EINE Alarm-Mail (Spam-Schutz)
 *   - Preis über Ziel → Re-Arm; erneutes Unterschreiten → neue Mail
 *   - Command überspringt frisch bewertete Einträge
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Wishlist\ValuateWishlistItemAction;
use App\Mail\WishlistPriceAlertMail;
use App\Models\Brand;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Perplexity-Antwort mit gewünschtem Marktwert.
 */
function marketValueResponse(int $value): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => json_encode([
                    'market_value_eur' => $value,
                    'value_low_eur' => $value - 200,
                    'value_high_eur' => $value + 400,
                    'summary' => 'Stabiler Markt mit guter Nachfrage.',
                    'source_urls' => [],
                ]),
            ],
        ]],
        'citations' => [],
    ];
}

it('valuates wishlist items and alerts once per target undercut', function () {
    $tenant = provisionTenant();

    $tenant->update(['notification_email' => 'verkauf@example.test']);

    try {
        $tenant->run(function () {
            config(['services.perplexity.api_key' => 'test-key']);
            Mail::fake();

            Http::fake([
                'api.perplexity.ai/*' => Http::sequence()
                    ->push(marketValueResponse(4400))  // unter Ziel → Mail
                    ->push(marketValueResponse(4400))  // weiter unter Ziel → KEINE zweite Mail
                    ->push(marketValueResponse(4800))  // über Ziel → Re-Arm
                    ->push(marketValueResponse(4300)), // wieder unter Ziel → neue Mail
            ]);

            $item = WishlistItem::create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Explorer',
                'reference_number' => '124270',
                'target_price' => 4500,
            ]);

            $action = app(ValuateWishlistItemAction::class);

            // 1. Bewertung: Ziel erreicht → Alarm-Mail
            $item = $action->execute($item);

            expect((float) $item->current_market_value)->toBe(4400.0)
                ->and((float) $item->value_low)->toBe(4200.0)
                ->and($item->isTargetReached())->toBeTrue()
                ->and($item->getAttribute('notified_at'))->not->toBeNull();

            Mail::assertSent(WishlistPriceAlertMail::class, 1);
            Mail::assertSent(WishlistPriceAlertMail::class, function (WishlistPriceAlertMail $mail): bool {
                $mail->assertTo('verkauf@example.test');

                $html = $mail->render();

                return str_contains($html, 'Zielpreis erreicht')
                    && str_contains($html, 'Explorer')
                    && str_contains($html, '4.400')
                    && str_contains($html, '4.500');
            });

            // 2. Bewertung: weiter unter Ziel → KEINE zweite Mail
            $action->execute($item);
            Mail::assertSent(WishlistPriceAlertMail::class, 1);

            // 3. Bewertung: über Ziel → Re-Arm
            $item = $action->execute($item);

            expect($item->isTargetReached())->toBeFalse()
                ->and($item->getAttribute('notified_at'))->toBeNull();

            // 4. Bewertung: erneut unter Ziel → zweite Mail
            $action->execute($item);
            Mail::assertSent(WishlistPriceAlertMail::class, 2);

            // Command: frisch bewertet → wird übersprungen
            $this->artisan('wishlist:update-values')
                ->expectsOutputToContain('Keine Wunschlisten-Einträge zur Bewertung fällig.')
                ->assertExitCode(0);
        });
    } finally {
        destroyTenant($tenant);
    }
});
