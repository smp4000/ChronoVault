<?php

/**
 * =========================================================================
 * CentralAuthorizationTest — Gate-Checks im zentralen Kontext (ohne Tenant)
 * =========================================================================
 *
 * Regressionstest für den spatie-Gate::before-Hook:
 *   Der Hook ruft User::checkPermissionTo() bei JEDEM Gate-Check auf —
 *   auch im zentralen Admin-Panel. Die permission-Tabellen existieren
 *   aber nur in Tenant-Datenbanken. Ohne den Tenant-Kontext-Guard im
 *   User-Model crashte jeder zentrale Policy-Check mit
 *   "Table 'chronovault.permissions' doesn't exist".
 *
 * Die zentrale Test-DB (sqlite :memory:) hat wie die echte zentrale DB
 * KEINE permission-Tabellen — diese Tests schlagen ohne den Guard fehl.
 * =========================================================================
 */

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

it('authorizes central users through policies without permission tables', function () {
    $user = User::factory()->create();

    // Policy-Check (TenantPolicy) muss durchlaufen, nicht am
    // spatie-Hook zerschellen.
    expect($user->can('viewAny', Tenant::class))->toBeTrue()
        ->and($user->can('create', Tenant::class))->toBeTrue();
});

it('denies raw permission checks in central context instead of crashing', function () {
    $user = User::factory()->create();

    // Ohne Tenant-Kontext gibt es keine spatie-Berechtigungen.
    expect($user->can('master_data.view'))->toBeFalse()
        ->and($user->checkPermissionTo('users.create'))->toBeFalse();
});
