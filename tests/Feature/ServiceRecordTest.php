<?php

/**
 * =========================================================================
 * ServiceRecordTest — Service-Historie & Wartung (Modul 6)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Start: Vorgang angelegt, Status gemerkt, Uhr → "Im Service"
 *   - Abschluss: Status-RESTORE (Kommissionsuhr kommt als Kommission zurück)
 *   - Kein Restore, wenn die Uhr zwischenzeitlich anders vergeben wurde
 *   - Berechtigungen (services.*) je Rolle
 *   - Referenz-Schutz: Werkstatt-Kontakt mit Vorgängen nicht löschbar
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Services\CompleteServiceAction;
use App\Actions\Services\StartServiceAction;
use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\User;
use App\Models\Watch;

it('starts a service, remembers the previous status and restores it on completion', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            // Kommissionsuhr — muss nach dem Service wieder Kommission sein!
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'status' => WatchStatus::Consignment,
            ]);
            $workshop = Contact::factory()->dealer()->create();

            $record = app(StartServiceAction::class)->execute($watch, [
                'type' => 'full_service',
                'contact_id' => $workshop->id,
                'description' => 'Komplette Revision',
            ]);

            expect($record->status)->toBe(ServiceStatus::InProgress)
                ->and($record->previous_watch_status)->toBe(WatchStatus::Consignment)
                ->and($watch->refresh()->status)->toBe(WatchStatus::InService);

            app(CompleteServiceAction::class)->execute($record, [
                'completed_at' => '2026-07-08',
                'cost' => 850,
                'warranty_until' => '2028-07-08',
            ]);

            $record->refresh();

            expect($record->status)->toBe(ServiceStatus::Completed)
                ->and($record->cost)->toBe('850.00')
                ->and($record->completed_at->toDateString())->toBe('2026-07-08')
                ->and($watch->refresh()->status)->toBe(WatchStatus::Consignment);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('does not override the watch status if it changed while in service', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            $record = app(StartServiceAction::class)->execute($watch, ['type' => 'repair']);

            // Zwischenzeitlich verkauft (Sonderfall) — Restore darf das
            // NICHT überschreiben.
            $watch->refresh()->forceFill(['status' => WatchStatus::Sold])->saveQuietly();

            app(CompleteServiceAction::class)->execute($record);

            expect($watch->refresh()->status)->toBe(WatchStatus::Sold);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('grants service permissions according to role semantics', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $employee = User::factory()->create();
            $employee->assignRole(UserRole::Employee->value);

            $viewer = User::factory()->create();
            $viewer->assignRole(UserRole::Viewer->value);

            expect($employee->can('services.create'))->toBeTrue()
                ->and($employee->can('services.update'))->toBeTrue()
                ->and($employee->can('services.delete'))->toBeFalse()
                ->and($viewer->can('services.view'))->toBeTrue()
                ->and($viewer->can('services.create'))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('protects workshop contacts with service records from deletion', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $owner = User::firstOrFail();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);
            $workshop = Contact::factory()->create();

            app(StartServiceAction::class)->execute($watch, [
                'type' => 'repair',
                'contact_id' => $workshop->id,
            ]);

            expect($owner->can('delete', $workshop))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});
