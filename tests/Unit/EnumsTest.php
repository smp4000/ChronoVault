<?php

/**
 * Unit-Tests der Domänen-Enums: Deutsche Labels und Rollen-Semantik.
 * Schützt vor versehentlichen Umbenennungen der Code-Werte (die in
 * Tenant-Datenbanken persistiert sind!).
 */

declare(strict_types=1);

use App\Enums\BraceletMaterial;
use App\Enums\CaseMaterial;
use App\Enums\ClaspType;
use App\Enums\ContactType;
use App\Enums\DialNumerals;
use App\Enums\GlassType;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Enums\TenantStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\WatchColor;
use App\Enums\WatchCondition;
use App\Enums\WatchGender;
use App\Enums\WatchStatus;

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
        ->and(MovementType::SpringDrive->getLabel())->toBe('Spring Drive')
        ->and(MovementType::Smartwatch->getLabel())->toBe('Smartwatch');
});

it('keeps stable chrono24 attribute enum values with german labels', function () {
    expect(CaseMaterial::Steel->value)->toBe('steel')
        ->and(CaseMaterial::Steel->getLabel())->toBe('Stahl')
        ->and(CaseMaterial::GoldSteel->getLabel())->toBe('Gold/Stahl')
        ->and(WatchColor::MotherOfPearl->value)->toBe('mother_of_pearl')
        ->and(WatchColor::MotherOfPearl->getLabel())->toBe('Perlmutt')
        ->and(BraceletMaterial::CrocodileLeather->getLabel())->toBe('Krokodilleder')
        ->and(WatchGender::Mens->getLabel())->toBe('Herrenuhr')
        ->and(GlassType::Sapphire->getLabel())->toBe('Saphirglas')
        ->and(ClaspType::PinBuckle->getLabel())->toBe('Dornschließe')
        ->and(DialNumerals::Indices->getLabel())->toBe('Indizes/Striche');
});

it('keeps stable transaction enums with german labels', function () {
    expect(TransactionType::Purchase->value)->toBe('purchase')
        ->and(TransactionType::Purchase->getLabel())->toBe('Ankauf')
        ->and(TransactionType::Sale->getLabel())->toBe('Verkauf')
        ->and(PaymentMethod::BankTransfer->value)->toBe('bank_transfer')
        ->and(PaymentMethod::BankTransfer->getLabel())->toBe('Überweisung')
        ->and(PaymentMethod::TradeIn->getLabel())->toBe('Inzahlungnahme')
        ->and(ContactType::PrivatePerson->value)->toBe('private')
        ->and(ContactType::PrivatePerson->getLabel())->toBe('Privatperson')
        ->and(ContactType::AuctionHouse->getLabel())->toBe('Auktionshaus');
});

it('keeps stable service enums with german labels', function () {
    expect(ServiceType::FullService->value)->toBe('full_service')
        ->and(ServiceType::FullService->getLabel())->toBe('Revision (Komplettservice)')
        ->and(ServiceType::WaterResistanceTest->getLabel())->toBe('Wasserdichtigkeitsprüfung')
        ->and(ServiceStatus::Open->value)->toBe('open')
        ->and(ServiceStatus::InProgress->getLabel())->toBe('In Arbeit')
        ->and(ServiceStatus::Completed->getLabel())->toBe('Abgeschlossen')
        ->and(ContactType::Workshop->getLabel())->toBe('Werkstatt/Service');
});

it('keeps stable watch condition values with german labels', function () {
    expect(WatchCondition::New->value)->toBe('new')
        ->and(WatchCondition::VeryGood->value)->toBe('very_good')
        ->and(WatchCondition::New->getLabel())->toBe('Neu')
        ->and(WatchCondition::Unworn->getLabel())->toBe('Ungetragen')
        ->and(WatchCondition::VeryGood->getLabel())->toBe('Sehr gut')
        ->and(WatchCondition::Good->getLabel())->toBe('Gut')
        ->and(WatchCondition::Fair->getLabel())->toBe('Getragen');
});

it('keeps stable watch status values with german labels and sellable semantics', function () {
    expect(WatchStatus::InStock->value)->toBe('in_stock')
        ->and(WatchStatus::InStock->getLabel())->toBe('An Lager')
        ->and(WatchStatus::Reserved->getLabel())->toBe('Reserviert')
        ->and(WatchStatus::InService->getLabel())->toBe('Im Service')
        ->and(WatchStatus::Consignment->getLabel())->toBe('Kommission')
        ->and(WatchStatus::Sold->getLabel())->toBe('Verkauft')
        ->and(WatchStatus::sellableStatuses())->toBe([WatchStatus::InStock, WatchStatus::Consignment]);
});

it('keeps stable tenant status values with german labels', function () {
    expect(TenantStatus::Active->value)->toBe('active')
        ->and(TenantStatus::Active->getLabel())->toBe('Aktiv')
        ->and(TenantStatus::Trial->getLabel())->toBe('Testphase')
        ->and(TenantStatus::Suspended->getLabel())->toBe('Gesperrt')
        ->and(TenantStatus::Archived->getLabel())->toBe('Archiviert');
});
