<?php

/**
 * =========================================================================
 * LegalPagesTest — Rechtsseiten & DSGVO-Grundausstattung (Shop)
 * =========================================================================
 *
 * Abgedeckt:
 *   - /impressum, /datenschutz, /widerruf rendern die Betriebsdaten-Texte
 *   - Ohne Inhalt: deutlicher Hinweis statt leerer Seite
 *   - Footer verlinkt alle drei Rechtsseiten
 * =========================================================================
 */

declare(strict_types=1);
use App\Services\LegalTextService;
use Illuminate\Support\Facades\Http;

it('serves the legal pages with tenant content and footer links', function () {
    $tenant = provisionTenant();

    $tenant->update([
        'imprint' => "Muster Uhrenhandel GmbH\nUhrmacherweg 1\n10115 Berlin",
        'privacy_policy' => 'Wir verarbeiten Ihre Daten ausschließlich zur Abwicklung Ihrer Anfragen und Käufe.',
        // revocation_policy bewusst leer → Hinweis-Fallback
    ]);

    try {
        $base = 'http://'.$tenant->primaryDomain();

        // Impressum mit Inhalt
        $this->get($base.'/impressum')
            ->assertOk()
            ->assertSee('Impressum')
            ->assertSee('Muster Uhrenhandel GmbH');

        // Datenschutz mit Inhalt
        $this->get($base.'/datenschutz')
            ->assertOk()
            ->assertSee('Datenschutzerklärung')
            ->assertSee('zur Abwicklung Ihrer Anfragen');

        // Widerruf ohne Inhalt → Hinweis für den Betreiber
        $this->get($base.'/widerruf')
            ->assertOk()
            ->assertSee('Widerrufsbelehrung')
            ->assertSee('noch kein Inhalt hinterlegt');

        // Footer-Links auf der Shop-Startseite
        $this->get($base.'/')
            ->assertOk()
            ->assertSee('/impressum')
            ->assertSee('/datenschutz')
            ->assertSee('/widerruf');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('generates legal text drafts via the ai service', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            config(['services.perplexity.api_key' => 'test-key']);

            Http::fake([
                'api.perplexity.ai/*' => Http::response([
                    'choices' => [[
                        'message' => ['content' => "IMPRESSUM\n\nMuster Uhrenhandel GmbH\nUhrmacherweg 1"],
                    ]],
                ]),
            ]);

            $text = app(LegalTextService::class)->generate('imprint', [
                'company_name' => 'Muster Uhrenhandel GmbH',
                'legal_form' => 'GmbH',
                'owner_name' => 'Max Muster',
                'street' => 'Uhrmacherweg 1',
                'postal_code' => '10115',
                'city' => 'Berlin',
                'email' => 'info@example.test',
            ]);

            expect($text)->toContain('IMPRESSUM')
                ->and($text)->toContain('Muster Uhrenhandel GmbH');

            // Der Prompt enthaelt die Angaben + Plattform-Fakten
            Http::assertSent(function ($request): bool {
                $body = json_encode($request->data());

                return str_contains($body, 'Max Muster')
                    && str_contains($body, 'technisch notwendige')
                    && str_contains($body, 'Hetzner');
            });
        });
    } finally {
        destroyTenant($tenant);
    }
});
