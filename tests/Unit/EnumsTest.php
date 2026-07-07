<?php

/**
 * Unit-Tests der Domänen-Enums: Deutsche Labels und Rollen-Semantik.
 * Schützt vor versehentlichen Umbenennungen der Code-Werte (die in
 * Tenant-Datenbanken persistiert sind!).
 */

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\TenantStatus;
use App\Enums\UserRole;

it('keeps stable role values with german labels', function () {
    expect(UserRole::Owner->value)->toBe('owner')
        ->and(UserRole::Owner->getLabel())->toBe('Inhaber')
        ->and(UserRole::Admin->getLabel())->toBe('Administrator')
        ->and(UserRole::Employee->getLabel())->toBe('Mitarbeiter')
        ->and(UserRole::Viewer->getLabel())->toBe('Betrachter');
});

it('defines owner and admin as management roles', function () {
    expect(UserRole::managementRoles())->toBe([UserRole::Owner, UserRole::Admin]);
});

it('keeps stable movement type values with german labels', function () {
    expect(MovementType::Manual->value)->toBe('manual')
        ->and(MovementType::SpringDrive->value)->toBe('spring_drive')
        ->and(MovementType::Manual->getLabel())->toBe('Handaufzug')
        ->and(MovementType::Automatic->getLabel())->toBe('Automatik')
        ->and(MovementType::Quartz->getLabel())->toBe('Quarz')
        ->and(MovementType::Solar->getLabel())->toBe('Solar')
        ->and(MovementType::SpringDrive->getLabel())->toBe('Spring Drive');
});

it('keeps stable tenant status values with german labels', function () {
    expect(TenantStatus::Active->value)->toBe('active')
        ->and(TenantStatus::Active->getLabel())->toBe('Aktiv')
        ->and(TenantStatus::Trial->getLabel())->toBe('Testphase')
        ->and(TenantStatus::Suspended->getLabel())->toBe('Gesperrt')
        ->and(TenantStatus::Archived->getLabel())->toBe('Archiviert');
});
