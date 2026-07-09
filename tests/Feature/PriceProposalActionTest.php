<?php

/**
 * =========================================================================
 * PriceProposalActionTest — Annehmen (Zuschlag) & Gegenangebot (Shop)
 * =========================================================================
 *
 * Abgedeckt:
 *   - AcceptPriceProposalAction: Verkauf zum Wunschpreis, Käufer-Kontakt
 *     mit optionaler Adresse, Rechnung + Mail mit Anhängen, andere
 *     offene Vorschläge zur Uhr werden abgelehnt
 *   - Guard: nicht mehr verfügbare Uhr → RuntimeException
 *   - CounterPriceProposalAction: Status Gegenangebot, counter_price,
 *     CounterOfferMail an den Kunden (Reply-To Benachrichtigungs-Adresse)
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Shop\AcceptPriceProposalAction;
use App\Actions\Shop\CounterPriceProposalAction;
use App\Actions\Shop\SendProposalReplyAction;
use App\Enums\PriceProposalStatus;
use App\Enums\WatchStatus;
use App\Mail\CounterOfferMail;
use App\Mail\DealerReplyMail;
use App\Mail\ProposalAcceptedMail;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\PriceProposal;
use App\Models\Watch;
use App\Services\ProposalReplyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('accepts a proposal: sale at wish price, invoice mail, other proposals declined', function () {
    $tenant = provisionTenant();

    // Vollständige Betriebsdaten für Rechnung + Kaufvertrag
    $tenant->update([
        'bank_account_holder' => 'Test Uhrenhandel GmbH',
        'bank_iban' => 'DE02120300000000202051',
        'bank_bic' => 'BYLADEM1001',
        'company_street' => 'Uhrmacherweg 1',
        'company_postal_code' => '10115',
        'company_city' => 'Berlin',
        'tax_number' => '12/345/67890',
    ]);

    try {
        $tenant->run(function () {
            Mail::fake();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Vorschlag GMT',
                'reference_number' => 'M79830RB',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 4800,
            ]);

            $proposal = PriceProposal::create([
                'watch_id' => $watch->id,
                'name' => 'Pauli Meier',
                'email' => 'pauli@example.test',
                'proposed_price' => 4000,
                'asking_price_at_time' => 4800,
            ]);

            // Zweiter offener Vorschlag zur selben Uhr — wird hinfällig
            $rival = PriceProposal::create([
                'watch_id' => $watch->id,
                'name' => 'Max Bieter',
                'email' => 'max@example.test',
                'proposed_price' => 3500,
                'asking_price_at_time' => 4800,
            ]);

            app(AcceptPriceProposalAction::class)->execute($proposal, [
                'street' => 'Musterweg 12',
                'postal_code' => '12345',
                'city' => 'Berlin',
            ]);

            $buyer = Contact::where('email', 'pauli@example.test')->firstOrFail();
            $sale = $watch->transactions()->where('type', 'sale')->firstOrFail();

            expect($watch->refresh()->getAttribute('status'))->toBe(WatchStatus::Sold)
                ->and((float) $sale->price)->toBe(4000.0)
                ->and($sale->contact_id)->toBe($buyer->id)
                ->and($buyer->first_name)->toBe('Pauli')
                ->and($buyer->last_name)->toBe('Meier')
                ->and($buyer->street)->toBe('Musterweg 12')
                ->and($proposal->refresh()->getAttribute('status'))->toBe(PriceProposalStatus::Accepted)
                ->and($rival->refresh()->getAttribute('status'))->toBe(PriceProposalStatus::Declined);

            // Kunden-Mail: Zusage zum Wunschpreis + Rechnung/Kaufvertrag im Anhang
            Mail::assertSent(ProposalAcceptedMail::class, function (ProposalAcceptedMail $mail): bool {
                $mail->assertTo('pauli@example.test');

                $html = $mail->render();

                return str_contains($html, '4.000,00')
                    && str_contains($html, 'angenommen')
                    && $mail->invoice !== null
                    && str_starts_with($mail->invoice->invoice_number, 'RE-')
                    && count($mail->attachments()) === 2;
            });

            // Verkaufte Uhr: erneutes Annehmen des Rivalen unmöglich
            expect(fn () => app(AcceptPriceProposalAction::class)->execute($rival->refresh()))
                ->toThrow(RuntimeException::class);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('sends a counter offer with the dealer price and message', function () {
    $tenant = provisionTenant();

    $tenant->update(['notification_email' => 'verkauf@example.test']);

    try {
        $tenant->run(function () {
            Mail::fake();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Konter GMT',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 4800,
            ]);

            $proposal = PriceProposal::create([
                'watch_id' => $watch->id,
                'name' => 'Pauli Meier',
                'email' => 'pauli@example.test',
                'proposed_price' => 4000,
                'asking_price_at_time' => 4800,
            ]);

            app(CounterPriceProposalAction::class)->execute($proposal, 4500.0, 'Mit Versand inklusive.');

            expect($proposal->refresh()->getAttribute('status'))->toBe(PriceProposalStatus::Countered)
                ->and((float) $proposal->counter_price)->toBe(4500.0);

            Mail::assertSent(CounterOfferMail::class, function (CounterOfferMail $mail): bool {
                $mail->assertTo('pauli@example.test');
                $mail->assertHasReplyTo('verkauf@example.test');

                $html = $mail->render();

                return str_contains($html, '4.500')
                    && str_contains($html, '4.000')
                    && str_contains($html, 'Mit Versand inklusive.')
                    && str_contains($html, 'Konter GMT');
            });

            // Gegenangebot bleibt offen — Annehmen danach weiter möglich
            expect($proposal->getAttribute('status')->isOpen())->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('drafts an ai reply and sends the dealer reply mail', function () {
    $tenant = provisionTenant();

    $tenant->update(['notification_email' => 'verkauf@example.test']);

    try {
        $tenant->run(function () {
            config(['services.perplexity.api_key' => 'test-key']);

            Http::fake([
                'api.perplexity.ai/*' => Http::response([
                    'choices' => [[
                        'message' => ['content' => "Sehr geehrter Herr Meier,\n\nvielen Dank fuer Ihren Vorschlag.\n\nMit freundlichen Gruessen"],
                    ]],
                ]),
            ]);

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Antwort GMT',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 4800,
            ]);

            $proposal = PriceProposal::create([
                'watch_id' => $watch->id,
                'name' => 'Pauli Meier',
                'email' => 'pauli@example.test',
                'proposed_price' => 4000,
                'asking_price_at_time' => 4800,
                'message' => 'machst du letzte preis bitte',
            ]);

            // KI-Entwurf (Perplexity-Weg, gefakt)
            $draft = app(ProposalReplyService::class)->draft($proposal, 'firm', 'Service 2024 gemacht');

            expect($draft)->toContain('Sehr geehrter Herr Meier');

            // Versand der (angepassten) Antwort
            Mail::fake();

            app(SendProposalReplyAction::class)->execute(
                $proposal,
                'Ihr Preisvorschlag zu Rolex Antwort GMT',
                $draft,
            );

            Mail::assertSent(DealerReplyMail::class, function (DealerReplyMail $mail): bool {
                $mail->assertTo('pauli@example.test');
                $mail->assertHasReplyTo('verkauf@example.test');
                $mail->assertHasSubject('Ihr Preisvorschlag zu Rolex Antwort GMT');

                $html = $mail->render();

                return str_contains($html, 'Sehr geehrter Herr Meier')
                    && str_contains($html, 'Antwort GMT');
            });
        });
    } finally {
        destroyTenant($tenant);
    }
});
