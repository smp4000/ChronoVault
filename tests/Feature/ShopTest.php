<?php

/**
 * =========================================================================
 * ShopTest — Öffentliches Schaufenster (Shop auf der Tenant-Domain)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Listing zeigt veröffentlichte Uhren; Verkauft/Reserviert bleiben
 *     mit Badge sichtbar, Unveröffentlichtes nie
 *   - Preisanzeige (formatiert) vs. „Preis auf Anfrage"
 *   - Markenfilter (?marke=<brand_id>)
 *   - Detailseite: 200 für veröffentlichte (auch verkaufte, mit Badge),
 *     404 für unveröffentlichte (Interna bleiben unsichtbar)
 *
 * WICHTIG (Muster aus WatchPhotoDownloadTest): HTTP-Requests auf die
 * Tenant-Domain initialisieren Tenancy und beenden sie nicht — ohne
 * tenancy()->end() im finally räumt PHPUnit auf der bereits gelöschten
 * Tenant-Verbindung auf und maskiert das echte Testergebnis.
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\PriceProposalStatus;
use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PriceProposalMail;
use App\Mail\WatchInquiryMail;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\PriceProposal;
use App\Models\Watch;
use Illuminate\Support\Facades\Mail;

it('lists published watches and shows sold ones with a badge', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Sichtbare Submariner',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 12500,
            ]);

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Unveroeffentlichte GMT',
                'status' => WatchStatus::InStock,
                'is_published' => false,
            ]);

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Verkaufte Daytona',
                'status' => WatchStatus::Sold,
                'is_published' => true,
            ]);
        });

        $response = $this->get('http://'.$tenant->primaryDomain().'/');

        // Verkaufte Uhren bleiben als Referenz sichtbar — mit Badge
        $response->assertOk()
            ->assertSee('Sichtbare Submariner')
            ->assertSee('12.500')
            ->assertDontSee('Unveroeffentlichte GMT')
            ->assertSee('Verkaufte Daytona')
            ->assertSee('Verkauft');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('shows price on request when no asking price is set', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Preislose Explorer',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => null,
            ]);
        });

        $this->get('http://'.$tenant->primaryDomain().'/')
            ->assertOk()
            ->assertSee('Preislose Explorer')
            ->assertSee('Preis auf Anfrage');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('filters the shop listing by brand', function () {
    $tenant = provisionTenant();

    try {
        $omegaId = null;

        $tenant->run(function () use (&$omegaId) {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();
            $omega = Brand::where('name', 'Omega')->firstOrFail();
            $omegaId = $omega->id;

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Rolex Modell',
                'status' => WatchStatus::InStock,
                'is_published' => true,
            ]);

            Watch::factory()->create([
                'brand_id' => $omega->id,
                'model_name' => 'Omega Modell',
                'status' => WatchStatus::InStock,
                'is_published' => true,
            ]);
        });

        $this->get('http://'.$tenant->primaryDomain().'/?marke='.$omegaId)
            ->assertOk()
            ->assertSee('Omega Modell')
            ->assertDontSee('Rolex Modell');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('shows the detail page for a published watch', function () {
    $tenant = provisionTenant();

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Detail Submariner',
                'reference_number' => '126610LN',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 13900.50,
            ]);
            $watchId = $watch->id;
        });

        $this->get('http://'.$tenant->primaryDomain().'/uhren/'.$watchId)
            ->assertOk()
            ->assertSee('Detail Submariner')
            ->assertSee('126610LN')
            ->assertSee('13.900,50')
            ->assertSee('Technische Daten');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('sends watch inquiries to the shop owner with reply-to the customer', function () {
    $tenant = provisionTenant();

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watchId = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Anfrage Submariner',
                'status' => WatchStatus::InStock,
                'is_published' => true,
            ])->id;
        });

        Mail::fake();

        $url = 'http://'.$tenant->primaryDomain().'/uhren/'.$watchId;

        // Gültige Anfrage → Mail an den Inhaber, Reply-To Kunde
        $this->from($url)
            ->post($url.'/anfrage', [
                'name' => 'Erika Mustermann',
                'email' => 'erika@example.test',
                'phone' => '+49 170 1234567',
                'message' => 'Ist die Uhr noch verfügbar?',
            ])
            ->assertRedirect($url)
            ->assertSessionHas('inquiry_success');

        Mail::assertSent(
            WatchInquiryMail::class,
            function (WatchInquiryMail $mail): bool {
                // Inhaber des Test-Tenants (provisionTenant)
                $mail->assertTo('owner@example.test');
                $mail->assertHasReplyTo('erika@example.test');

                $html = $mail->render();

                return str_contains($html, 'Anfrage Submariner')
                    && str_contains($html, 'Ist die Uhr noch verfügbar?');
            },
        );

        // Unvollständige Anfrage → Validierungsfehler, keine Mail
        $this->from($url)
            ->post($url.'/anfrage', ['name' => '', 'email' => 'kaputt', 'message' => ''])
            ->assertRedirect($url)
            ->assertSessionHasErrors(['name', 'email', 'message']);

        Mail::assertSent(WatchInquiryMail::class, 1);
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('handles a binding purchase: sale, invoice, contact, both mails with payment info', function () {
    $tenant = provisionTenant();

    // Vollständige Betriebsdaten, damit Rechnung + Kaufvertrag entstehen
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
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watchId = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Kauf Submariner',
                'reference_number' => '126610LN',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 8500,
            ])->id;
        });

        Mail::fake();

        $domain = $tenant->primaryDomain();
        $buyUrl = 'http://'.$domain.'/uhren/'.$watchId.'/kaufen';

        // Kaufseite erreichbar, zahlungspflichtig-Button vorhanden
        $this->get($buyUrl)
            ->assertOk()
            ->assertSee('zahlungspflichtig kaufen')
            ->assertSee('8.500,00');

        // Kauf ausführen — Redirect zum Shop-Katalog (die Kaufseite
        // existiert für die nun verkaufte Uhr nicht mehr)
        $this->from($buyUrl)
            ->post($buyUrl, [
                'first_name' => 'Erika',
                'last_name' => 'Mustermann',
                'email' => 'erika@example.test',
                'street' => 'Musterweg 12',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'Deutschland',
                'accept_binding' => '1',
            ])
            ->assertRedirect('http://'.$domain)
            ->assertSessionHas('purchase_success');

        $tenant->run(function () use ($watchId) {
            $watch = Watch::findOrFail($watchId);
            $buyer = Contact::where('email', 'erika@example.test')->firstOrFail();

            // Verbindlicher Kauf = Verkaufsbeleg sofort, Uhr Verkauft
            expect($watch->getAttribute('status'))->toBe(WatchStatus::Sold)
                ->and($buyer->street)->toBe('Musterweg 12')
                ->and($watch->transactions()->where('type', 'sale')->count())->toBe(1);
        });

        // Käufer-Mail: verbindlich + Zahlungsdaten + Verwendungszweck
        // + Rechnung und Kaufvertrag als PDF-Anhänge
        Mail::assertSent(
            OrderConfirmationMail::class,
            function (OrderConfirmationMail $mail): bool {
                $mail->assertTo('erika@example.test');

                $html = $mail->render();

                return str_contains($html, 'verbindlichen Kauf')
                    && str_contains($html, '8.500,00')
                    && str_contains($html, 'DE02 1203')
                    && str_contains($html, 'Kauf 126610LN Mustermann')
                    && $mail->invoice !== null
                    && str_starts_with($mail->invoice->invoice_number, 'RE-')
                    && str_contains($html, $mail->invoice->invoice_number)
                    && count($mail->attachments()) === 2;
            },
        );

        // Händler-Mail an den Inhaber
        Mail::assertSent(
            OrderReceivedMail::class,
            fn (OrderReceivedMail $mail): bool => $mail->hasTo('owner@example.test'),
        );

        // Uhr ist verkauft → bleibt mit Badge im Shop, Kauf erneut unmöglich
        $this->get('http://'.$domain.'/')
            ->assertSee('Kauf Submariner')
            ->assertSee('Verkauft');
        $this->get($buyUrl)->assertNotFound();
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('returns 404 for unpublished watches but shows sold ones with a badge', function () {
    $tenant = provisionTenant();

    try {
        $unpublishedId = null;
        $soldId = null;

        $tenant->run(function () use (&$unpublishedId, &$soldId) {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();

            $unpublishedId = Watch::factory()->create([
                'brand_id' => $rolex->id,
                'status' => WatchStatus::InStock,
                'is_published' => false,
            ])->id;

            $soldId = Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Verkaufte Referenz-Daytona',
                'status' => WatchStatus::Sold,
                'is_published' => true,
                'asking_price' => 25000,
            ])->id;
        });

        $domain = $tenant->primaryDomain();

        // Unveröffentlicht bleibt unsichtbar (Interna)
        $this->get('http://'.$domain.'/uhren/'.$unpublishedId)->assertNotFound();

        // Verkauft bleibt als Referenz sichtbar — Badge statt Kauf-Button
        $this->get('http://'.$domain.'/uhren/'.$soldId)
            ->assertOk()
            ->assertSee('Verkaufte Referenz-Daytona')
            ->assertSee('Verkauft')
            ->assertDontSee('Jetzt verbindlich kaufen');

        // Kaufseite existiert für Verkauftes weiterhin nicht
        $this->get('http://'.$domain.'/uhren/'.$soldId.'/kaufen')->assertNotFound();
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('accepts price proposals with captcha and forwards them to the owner', function () {
    $tenant = provisionTenant();

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watchId = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Vorschlag Submariner',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 9000,
            ])->id;
        });

        Mail::fake();

        $url = 'http://'.$tenant->primaryDomain().'/uhren/'.$watchId;

        // Gueltiger Vorschlag -> Mail an den Inhaber, Reply-To Kunde
        $this->from($url)
            ->post($url.'/preisvorschlag', [
                'proposed_price' => 8200,
                'name' => 'Erika Mustermann',
                'email' => 'erika@example.test',
                'message' => 'Waere das fuer Sie machbar?',
                'captcha_a' => 3,
                'captcha_b' => 4,
                'captcha' => 7,
                'privacy' => '1',
            ])
            ->assertRedirect($url)
            ->assertSessionHas('proposal_success');

        Mail::assertSent(PriceProposalMail::class, function (PriceProposalMail $mail): bool {
            $mail->assertTo('owner@example.test');
            $mail->assertHasReplyTo('erika@example.test');

            $html = $mail->render();

            return str_contains($html, '8.200')
                && str_contains($html, 'Vorschlag Submariner')
                && str_contains($html, 'Waere das fuer Sie machbar?');
        });

        // Vorschlag ist zusätzlich im Panel sichtbar (persistiert, Status Neu)
        $tenant->run(function () use ($watchId) {
            $proposal = PriceProposal::firstOrFail();

            expect($proposal->watch_id)->toBe($watchId)
                ->and((float) $proposal->proposed_price)->toBe(8200.0)
                ->and((float) $proposal->asking_price_at_time)->toBe(9000.0)
                ->and($proposal->email)->toBe('erika@example.test')
                ->and($proposal->getAttribute('status'))->toBe(PriceProposalStatus::New);
        });

        // Falsche Rechenantwort -> Fehler, keine weitere Mail
        $this->from($url)
            ->post($url.'/preisvorschlag', [
                'proposed_price' => 8200,
                'name' => 'Erika Mustermann',
                'email' => 'erika@example.test',
                'captcha_a' => 3,
                'captcha_b' => 4,
                'captcha' => 9,
                'privacy' => '1',
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors(['captcha']);

        // Fehlende DSGVO-Einwilligung -> Fehler
        $this->from($url)
            ->post($url.'/preisvorschlag', [
                'proposed_price' => 8200,
                'name' => 'Erika Mustermann',
                'email' => 'erika@example.test',
                'captcha_a' => 3,
                'captcha_b' => 4,
                'captcha' => 7,
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors(['privacy']);

        Mail::assertSent(PriceProposalMail::class, 1);
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('filters the shop listing by condition and price range', function () {
    $tenant = provisionTenant();

    try {
        $conditionA = WatchCondition::cases()[0];
        $conditionB = WatchCondition::cases()[1];

        $tenant->run(function () use ($conditionA, $conditionB) {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;

            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Guenstige Uhr',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 800,
                'condition' => $conditionA,
            ]);

            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Teure Uhr',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 12000,
                'condition' => $conditionB,
            ]);
        });

        $base = 'http://'.$tenant->primaryDomain();

        // Preisfilter: bis 1.000 EUR zeigt nur die guenstige Uhr
        $this->get($base.'/?preis=bis1000')
            ->assertOk()
            ->assertSee('Guenstige Uhr')
            ->assertDontSee('Teure Uhr');

        // Zustandsfilter zeigt nur die passende Uhr
        $this->get($base.'/?zustand='.$conditionA->value)
            ->assertOk()
            ->assertSee('Guenstige Uhr')
            ->assertDontSee('Teure Uhr');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('shows a discount with strike price after a price reduction', function () {
    $tenant = provisionTenant();

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Reduzierte Datejust',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 6950,
            ]);

            // Preissenkung -> Observer merkt den Streichpreis
            $watch->update(['asking_price' => 6500]);

            expect((float) $watch->refresh()->previous_asking_price)->toBe(6950.0)
                ->and($watch->discountPercent())->toBe(6);

            $watchId = $watch->id;
        });

        $domain = $tenant->primaryDomain();

        // Detailseite: roter Preis, Streichpreis, Ersparnis, 30-Tage-Hinweis
        $this->get('http://'.$domain.'/uhren/'.$watchId)
            ->assertOk()
            ->assertSee('6.500')
            ->assertSee('6.950')
            ->assertSee('Sie sparen 450,00')
            ->assertSee('Preis der letzten 30 Tage vor Preissenkung');

        // Listing: Rabatt-Badge auf der Kachel
        $this->get('http://'.$domain.'/')
            ->assertOk()
            ->assertSee('Reduzierte Datejust')
            ->assertSee('6 %');

        // Preiserhoehung setzt den Streichpreis zurueck
        $tenant->run(function () use ($watchId) {
            $watch = Watch::findOrFail($watchId);
            $watch->update(['asking_price' => 7200]);

            expect($watch->refresh()->previous_asking_price)->toBeNull()
                ->and($watch->discountPercent())->toBeNull();
        });
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('sends shop notifications to the configured notification email', function () {
    $tenant = provisionTenant();

    // Betriebsdaten: eigene Benachrichtigungs-Adresse hat Vorrang vor Rollen
    $tenant->update(['notification_email' => 'verkauf@example.test']);

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watchId = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Benachrichtigungs-Uhr',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 5000,
            ])->id;
        });

        Mail::fake();

        $url = 'http://'.$tenant->primaryDomain().'/uhren/'.$watchId;

        $this->from($url)
            ->post($url.'/anfrage', [
                'name' => 'Erika Mustermann',
                'email' => 'erika@example.test',
                'message' => 'Ist die Uhr verfuegbar?',
            ])
            ->assertRedirect($url);

        Mail::assertSent(
            WatchInquiryMail::class,
            fn (WatchInquiryMail $mail): bool => $mail->hasTo('verkauf@example.test')
                && ! $mail->hasTo('owner@example.test'),
        );
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});
