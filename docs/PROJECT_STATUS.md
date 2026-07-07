# ChronoVault — Projektstatus

> **Diese Datei ist die Single Source of Truth für den Projektstatus.**
> Sie wird nach JEDEM abgeschlossenen Arbeitsschritt aktualisiert und dient als
> Statusblock-Quelle am Anfang jeder Entwicklungs-Session.
>
> Letzte Aktualisierung: 2026-07-07 (Modul 1 — Tenancy & Benutzerverwaltung)

---

## Aktueller Stand

**Modul 1 (Tenancy & Benutzer-/Rollenverwaltung) abgeschlossen.**
Multi-Database-Tenancy läuft end-to-end: Mandanten-Provisioning (DB +
Migrationen + Rollen-Seed + Domain + Owner) über das zentrale Admin-Panel,
Tenant-Panel unter `{slug}.localhost:8000/app`, 10 Tests grün, PHPStan Level 6 sauber.

**Nächster Schritt:** Modul 2 — Stammdaten: Marken (Brands) & Kaliber.

---

## Module

| # | Modul | Status |
|---|-------|--------|
| 0 | Foundation (Scaffold, Pakete, Panel, Doku) | ✅ Fertig |
| 1 | Tenancy & Benutzer-/Rollenverwaltung ([Doku](modules/module-01-tenancy.md)) | ✅ Fertig |
| 2 | Stammdaten: Marken (Brands) & Kaliber | ⬜ Offen |
| 3 | Kernmodul: Uhren (Watches) | ⬜ Offen |
| 4 | Medienverwaltung (Fotos, Zertifikate, Dokumente) | ⬜ Offen |
| 5 | Kauf/Verkauf & Preishistorie | ⬜ Offen |
| 6 | Service-Historie & Wartung | ⬜ Offen |
| 7 | Bewertungen & Marktwert | ⬜ Offen |
| 8 | Auktionen | ⬜ Offen |
| 9 | Reporting & Dashboards | ⬜ Offen |
| 10 | API (Sanctum) & Integrationen | ⬜ Offen |

## Datenbanktabellen

**Zentral (MariaDB `chronovault`):**
- `tenants` (UUID, name, slug, status, data, SoftDeletes), `domains`
- `users` (Plattform-Betreiber), `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `telescope_*`

**Pro Tenant (`cv_tenant_<uuid>`, Migrationen in `database/migrations/tenant/`):**
- `users`, `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

## Models

- `App\Models\User` — zentral UND tenant (Connection-Switch); FilamentUser + HasRoles
- `App\Models\Tenant` — stancl BaseTenant + SoftDeletes, Custom Columns (name, slug, status)

## Filament Resources

**Central-Panel (`/admin`, Namespace `App\Filament\Central`):**
- `Tenants\TenantResource` (+ TenantForm, TenantsTable, List/Create/Edit-Pages)

**App-Panel (`/app` auf Tenant-Domains, Namespace `App\Filament\App`):**
- `Users\UserResource` (+ UserForm, UsersTable, List/Create/Edit-Pages)

**Widgets:**
- `Central\Widgets\TenantStatsWidget` (Mandanten-Kennzahlen, Dashboard)

## Services

- _Noch keine_ (bisher reichten Actions)

## Actions

- `App\Actions\Tenancy\CreateTenantAction` — komplettes Provisioning
- `App\Actions\Tenancy\DeleteTenantAction` — archive() (Soft) / execute() (endgültig + DB-Löschung)

## Enums

- `App\Enums\TenantStatus` (trial/active/suspended/archived, deutsche Labels, Filament-Contracts)
- `App\Enums\UserRole` (owner/admin/employee/viewer, deutsche Labels, managementRoles())

## Jobs

- _Eigene: keine._ Genutzt werden stancl-Jobs: CreateDatabase, MigrateDatabase, SeedDatabase, DeleteDatabase

## Events

- _Eigene: keine._ stancl-Events via TenancyServiceProvider (TenantCreated-Pipeline; TenantDeleted bewusst OHNE DB-Löschung)

## Policies

- `App\Policies\TenantPolicy` — nur zentraler Kontext; forceDelete nur für archivierte
- `App\Policies\UserPolicy` — permission-basiert (users.*), Selbstlöschungs- & Owner-Hierarchie-Schutz

## Observers

- `App\Observers\TenantObserver` — Slug-Generierung + Kollisionsauflösung (creating)

## Seeder / Factories

- `Database\Seeders\TenantDatabaseSeeder` — Rollen + Basis-Berechtigungen (users.*, roles.manage, settings.manage); wird bei jedem Provisioning ausgeführt
- `Database\Factories\TenantFactory` (+ UserFactory aus dem Skeleton)

## Offene TODOs

- [ ] Modul 2: Marken & Kaliber (Tenant-Migrationen!)
- [ ] Berechtigungen neuer Module immer im TenantDatabaseSeeder ergänzen + `tenants:seed` für Bestandsmandanten
- [ ] RoleResource im App-Panel (eigene Rollen pro Mandant; Berechtigung `roles.manage` existiert)
- [ ] Suspended-Tenant-UX: Login wird verweigert (canAccessPanel), aber ohne erklärende Fehlerseite
- [ ] Willkommens-E-Mail für neue Mandanten-Owner (statt Initialpasswort-Übergabe)
- [ ] Redis in Produktion: CacheTenancyBootstrapper aktivieren, permission-Cache zurück auf persistent (ADR-008), Horizon (ADR-002)
- [ ] Meilisearch lokal installieren, Scout-Driver umstellen (ADR-003)
- [ ] Laravel Pulse konfigurieren; Telescope in Produktion deaktivieren
- [ ] Deutsches Sprachpaket (`laravel-lang`) für Framework-Validierungsmeldungen
- [ ] Eigenes Filament-Theme-CSS (`->viteTheme()`) für Premium-Feinschliff

## Mögliche zukünftige Verbesserungen

- Self-Service-Registrierung + Onboarding-Wizard für neue Mandanten
- User Impersonation (stancl Feature) für Support
- Kunden-Portal / öffentlicher Marktplatz, KI-Preisbewertung, Mobile-API
- Audit-Exporte (Versicherung), QR-Etiketten fürs Lager, Webhooks
- Backup vor endgültiger Tenant-Löschung; Lösch-Karenzzeit
