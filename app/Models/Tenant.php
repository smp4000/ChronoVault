<?php

/**
 * =========================================================================
 * Tenant — Mandanten-Model (zentrale Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Repräsentiert einen Mandanten (Händler, Juwelier, Auktionshaus) der
 *   Plattform. Erweitert das stancl/tenancy-Basis-Model um echte Spalten
 *   (name, slug, status) und SoftDeletes.
 *
 * Verantwortlichkeiten:
 *   - Custom Columns deklarieren (alles andere landet in der JSON-Spalte "data")
 *   - Domains-Beziehung (HasDomains) für die Domain-Identifikation
 *   - Statuslogik (isActive) für Zugriffsprüfungen
 *
 * Abhängigkeiten:
 *   - stancl/tenancy (BaseTenant, TenantWithDatabase)
 *   - App\Enums\TenantStatus
 *   - App\Observers\TenantObserver (registriert via #[ObservedBy])
 *
 * WARUM getCustomColumns():
 *   stancl speichert unbekannte Attribute in der JSON-Spalte "data".
 *   Nur hier gelistete Attribute werden als echte DB-Spalten behandelt —
 *   wichtig für Indizes, Unique-Constraints und Abfragen.
 *
 * Mögliche Erweiterungen:
 *   - plan/billing-Spalten (Modul Abrechnung)
 *   - Beziehung zu zentralen Audit-Logs
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use App\Observers\TenantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

#[ObservedBy([TenantObserver::class])]
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $casts = [
        'status' => TenantStatus::class,
        'data' => 'array',
    ];

    /**
     * Echte DB-Spalten (alles andere wandert in die JSON-Spalte "data").
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
        ];
    }

    /**
     * Darf dieser Mandant die Anwendung nutzen?
     * Wird z. B. beim Login im Tenant-Panel geprüft.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [TenantStatus::Active, TenantStatus::Trial], true);
    }

    /**
     * Primäre Domain des Mandanten (erste registrierte Domain).
     * Convenience für UI-Anzeige und Login-Links.
     */
    public function primaryDomain(): ?string
    {
        return $this->domains()->first()?->domain;
    }
}
