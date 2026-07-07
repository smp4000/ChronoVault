<?php

/**
 * =========================================================================
 * CreateTenant — Anlage-Seite: delegiert das Provisioning an die Action
 * =========================================================================
 *
 * Zweck:
 *   Überschreibt Filaments Standard-Erstellung, damit ein Mandant NIE
 *   „nackt" per Tenant::create() entsteht, sondern immer den vollen
 *   Provisioning-Pfad durchläuft (DB anlegen → migrieren → Rollen seeden
 *   → Domain registrieren → Owner-Benutzer anlegen).
 *
 * WARUM handleRecordCreation:
 *   Das ist der offizielle Filament-Hook für eigene Erstellungslogik.
 *   Die owner_*-Felder sind Formular-, keine Model-Daten — sie werden
 *   hier herausgelöst und an die CreateTenantAction übergeben.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Actions\Tenancy\CreateTenantAction;
use App\Enums\TenantStatus;
use App\Filament\Central\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Provisioning statt einfachem Model-Create.
     *
     * Läuft lokal synchron (einige Sekunden: DB anlegen + migrieren +
     * seeden) — Filament zeigt währenddessen den Loading-State des
     * Submit-Buttons.
     *
     * @param  array<string, mixed>  $data  Validierte Formulardaten
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTenantAction::class)->execute(
            name: $data['name'],
            ownerName: $data['owner_name'],
            ownerEmail: $data['owner_email'],
            ownerPassword: $data['owner_password'],
            slug: filled($data['slug'] ?? null) ? $data['slug'] : null,
            status: $data['status'] instanceof TenantStatus
                ? $data['status']
                : TenantStatus::from($data['status']),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Mandant angelegt — Datenbank, Rollen und Inhaber-Zugang wurden eingerichtet.';
    }
}
