<?php

/**
 * =========================================================================
 * MarketplaceOfferPageTest — Zentrale Angebotsseite (eBay-Prinzip)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Privat-Listings verlinken auf die zentrale Angebotsseite /angebot/…
 *   - Angebotsseite: Daten, Beschreibung, Privatverkaufs-Hinweis; ohne
 *     hinterlegte IBAN kein Sofortkauf (direct_buy false)
 *   - Anfrage und Preisvorschlag laufen zentral und landen beim
 *     Verkäufer-Mandanten (Mail bzw. PriceProposal in dessen DB)
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Tenancy\CreateTenantAction;
use App\Enums\WatchStatus;
use App\Mail\PriceProposalMail;
use App\Mail\WatchInquiryMail;
use App\Models\Brand;
use App\Models\MarketplaceListing;
use App\Models\PriceProposal;
use App\Models\Watch;
use Illuminate\Support\Facades\Mail;

it('serves private offers on the central platform with inquiry and price proposal', function () {
    $tenant = app(CreateTenantAction::class)->execute(
        name: 'Christian Weber',
        ownerName: 'Christian Weber',
        ownerEmail: 'weber@example.test',
        ownerPassword: 'SuperSicher!123',
        slug: 'weber-privat',
        sellerType: 'private',
    );

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watchId = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Omega')->firstOrFail()->id,
                'model_name' => 'Private Speedmaster',
                'reference_number' => '310.30.42',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 5800,
                'description' => 'Aus meiner privaten Sammlung, sehr gepflegt.',
            ])->id;
        });

        tenancy()->end();

        $listing = MarketplaceListing::query()->where('watch_id', $watchId)->firstOrFail();

        // Privat ohne IBAN: zentrale Angebotsseite, KEIN Sofortkauf
        expect($listing->detail_url)->toContain('/angebot/'.$listing->getKey())
            ->and($listing->direct_buy)->toBeFalse()
            ->and($listing->description)->toContain('privaten Sammlung');

        $url = 'http://localhost/angebot/'.$listing->getKey();

        $this->get($url)
            ->assertOk()
            ->assertSee('Private Speedmaster')
            ->assertSee('Privatverkauf')
            ->assertSee('Aus meiner privaten Sammlung')
            ->assertSee('Christian Weber')
            ->assertDontSee('Jetzt verbindlich kaufen');

        Mail::fake();

        // Anfrage → Mail an den Privatverkäufer (Owner)
        $this->from($url)
            ->post($url.'/anfrage', [
                'name' => 'Erika Mustermann',
                'email' => 'erika@example.test',
                'message' => 'Ist die Uhr noch zu haben?',
            ])
            ->assertRedirect($url)
            ->assertSessionHas('inquiry_success');

        Mail::assertSent(
            WatchInquiryMail::class,
            fn (WatchInquiryMail $mail): bool => $mail->hasTo('weber@example.test'),
        );

        tenancy()->end();

        // Preisvorschlag → landet in der Verkäufer-DB (Panel-Workflow!)
        $this->from($url)
            ->post($url.'/preisvorschlag', [
                'proposed_price' => 5200,
                'name' => 'Erika Mustermann',
                'email' => 'erika@example.test',
                'captcha_a' => 3,
                'captcha_b' => 4,
                'captcha' => 7,
                'privacy' => '1',
            ])
            ->assertRedirect($url)
            ->assertSessionHas('proposal_success');

        Mail::assertSent(PriceProposalMail::class);

        tenancy()->end();

        $tenant->run(function () use ($watchId) {
            $proposal = PriceProposal::firstOrFail();

            expect($proposal->watch_id)->toBe($watchId)
                ->and((float) $proposal->proposed_price)->toBe(5200.0);
        });

        // Mit hinterlegter IBAN wird der Sofortkauf möglich (direct_buy)
        $tenant->update(['bank_iban' => 'DE02120300000000202051']);

        $tenant->run(function () use ($watchId) {
            Watch::findOrFail($watchId)->touch();
        });

        tenancy()->end();

        expect($listing->refresh()->direct_buy)->toBeTrue();
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});
