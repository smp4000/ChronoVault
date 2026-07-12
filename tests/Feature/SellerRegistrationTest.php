<?php

/**
 * =========================================================================
 * SellerRegistrationTest — Selbst-Registrierung auf dem Marktplatz
 * =========================================================================
 *
 * Abgedeckt:
 *   - Formular erreichbar (zentrale Domain)
 *   - Registrierung als PRIVATER Verkäufer: Tenant + Domain + Owner-User
 *     entstehen, seller_type private; veröffentlichte Uhr trägt auf dem
 *     Marktplatz das „Privat"-Badge
 *   - Wunsch-Adresse: vergeben/reserviert → Validierungsfehler
 *   - Falsche Rechenantwort → kein Tenant
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\MarketplaceListing;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Watch;

it('registers a private seller with own domain and owner login', function () {
    $tenant = null;

    try {
        $this->get('http://localhost/verkaufen')
            ->assertOk()
            ->assertSee('Jetzt verkaufen')
            ->assertSee('Privat')
            ->assertSee('Gewerblich');

        // Privat: KEINE Geschäftsfelder — Name und Adresse entstehen
        // automatisch aus dem eigenen Namen
        $this->post('http://localhost/verkaufen', [
            'seller_type' => 'private',
            'owner_name' => 'Christian Weber',
            'email' => 'weber@example.test',
            'password' => 'SuperSicher!123',
            'password_confirmation' => 'SuperSicher!123',
            'captcha_a' => 2,
            'captcha_b' => 5,
            'captcha' => 7,
            'privacy' => '1',
        ])
            ->assertOk()
            ->assertSee('Willkommen, Christian Weber!')
            ->assertSee('christian-weber.localhost');

        $tenant = Tenant::query()->where('slug', 'christian-weber')->firstOrFail();

        expect($tenant->getAttribute('seller_type'))->toBe('private')
            ->and($tenant->getAttribute('name'))->toBe('Christian Weber')
            ->and($tenant->primaryDomain())->toBe('christian-weber.localhost');

        // Owner-Zugang existiert in der frischen Tenant-DB
        $tenant->run(function (): void {
            $owner = User::where('email', 'weber@example.test')->firstOrFail();

            expect($owner->hasRole(UserRole::Owner->value))->toBeTrue();
        });

        // Veröffentlichte Uhr des Privatverkäufers → Marktplatz mit „Privat"-Badge
        $tenant->run(function (): void {
            Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Private Submariner',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 9800,
            ]);
        });

        tenancy()->end();

        expect(
            MarketplaceListing::query()
                ->where('model_name', 'Private Submariner')
                ->value('seller_type'),
        )->toBe('private');

        $this->get('http://localhost/?suche=Private+Submariner')
            ->assertOk()
            ->assertSee('Private Submariner')
            ->assertSee('Privat');
    } finally {
        tenancy()->end();

        if ($tenant !== null) {
            destroyTenant($tenant);
        }
    }
});

it('rejects taken and reserved slugs and wrong captcha answers', function () {
    $existing = provisionTenant('Bestehender Handel', 'bestehender-handel');

    try {
        $base = [
            'seller_type' => 'private',
            'shop_name' => 'Testverkäufer',
            'owner_name' => 'Test Person',
            'email' => 'test@example.test',
            'password' => 'SuperSicher!123',
            'password_confirmation' => 'SuperSicher!123',
            'captcha_a' => 3,
            'captcha_b' => 4,
            'captcha' => 7,
            'privacy' => '1',
        ];

        // Vergebener Slug
        $this->from('http://localhost/verkaufen')
            ->post('http://localhost/verkaufen', array_merge($base, ['slug' => 'bestehender-handel']))
            ->assertRedirect('http://localhost/verkaufen')
            ->assertSessionHasErrors(['slug']);

        // Reservierter Slug
        $this->from('http://localhost/verkaufen')
            ->post('http://localhost/verkaufen', array_merge($base, ['slug' => 'admin']))
            ->assertRedirect('http://localhost/verkaufen')
            ->assertSessionHasErrors(['slug']);

        // Falsche Rechenantwort → kein neuer Tenant
        $this->from('http://localhost/verkaufen')
            ->post('http://localhost/verkaufen', array_merge($base, ['slug' => 'neuer-shop', 'captcha' => 9]))
            ->assertRedirect('http://localhost/verkaufen')
            ->assertSessionHasErrors(['captcha']);

        expect(Tenant::query()->where('slug', 'neuer-shop')->exists())->toBeFalse();
    } finally {
        tenancy()->end();
        destroyTenant($existing);
    }
});
