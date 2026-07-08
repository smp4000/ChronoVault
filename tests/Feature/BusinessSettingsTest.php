<?php

/**
 * =========================================================================
 * BusinessSettingsTest — Betriebsdaten-Seite (Bankverbindung)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Speichern der Bankverbindung ins zentrale Tenant-data-JSON
 *     (IBAN normalisiert: Leerzeichen raus, Großbuchstaben)
 *   - Zugriff nur mit settings.manage (Viewer sieht die Seite nicht)
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\App\Pages\BusinessSettings;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('saves the bank details normalized into the tenant data', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $this->actingAs(User::firstOrFail());
            Filament::setCurrentPanel(Filament::getPanel('app'));

            Livewire::test(BusinessSettings::class)
                ->fillForm([
                    'bank_account_holder' => 'Test Uhrenhandel GmbH',
                    'bank_iban' => 'de02 1203 0000 0000 2020 51',
                    'bank_bic' => 'byladem1001',
                ])
                ->call('save')
                ->assertHasNoErrors();

            expect(tenant('bank_iban'))->toBe('DE02120300000000202051')
                ->and(tenant('bank_bic'))->toBe('BYLADEM1001')
                ->and(tenant('bank_account_holder'))->toBe('Test Uhrenhandel GmbH');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('hides the settings page from users without settings.manage', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $viewer = User::factory()->create();
            $viewer->assignRole(UserRole::Viewer->value);
            $this->actingAs($viewer);

            expect(BusinessSettings::canAccess())->toBeFalse();

            $owner = User::firstOrFail();
            $this->actingAs($owner);

            expect(BusinessSettings::canAccess())->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});
