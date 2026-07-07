# ChronoVault — Projektstatus

> **Diese Datei ist die Single Source of Truth für den Projektstatus.**
> Sie wird nach JEDEM abgeschlossenen Arbeitsschritt aktualisiert und dient als
> Statusblock-Quelle am Anfang jeder Entwicklungs-Session.
>
> Letzte Aktualisierung: 2026-07-07 (Modul 3 — Kernmodul: Uhren)

---

## Aktueller Stand

**Modul 3 (Kernmodul: Uhren) abgeschlossen.**
Bestandstabelle `watches` (UUID + SoftDeletes, FKs auf brands/calibers),
Enums WatchCondition/WatchStatus, watches.*-Berechtigungen, WatchResource
in neuer Gruppe „Bestand" (abhängiges Kaliber-Select, Full-Set-Filter,
Papierkorb), WatchStatsWidget auf dem Tenant-Dashboard, Scout-Volltextsuche
(database-Driver, ADR-003), Referenz-Schutz in Brand-/CaliberPolicy
erweitert. 23 Tests grün, PHPStan Level 6 sauber.

**Nächster Schritt:** Modul 4 — Medienverwaltung (Fotos, Zertifikate, Dokumente).

---

## Module

| # | Modul | Status |
|---|-------|--------|
| 0 | Foundation (Scaffold, Pakete, Panel, Doku) | ✅ Fertig |
| 1 | Tenancy & Benutzer-/Rollenverwaltung ([Doku](modules/module-01-tenancy.md)) | ✅ Fertig |
| 2 | Stammdaten: Marken (Brands) & Kaliber ([Doku](modules/module-02-master-data.md)) | ✅ Fertig |
| 3 | Kernmodul: Uhren (Watches) ([Doku](modules/module-03-watches.md)) | ✅ Fertig |
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
- `brands` (UUID, name unique, country, founded_year, website, is_active, SoftDeletes)
- `calibers` (UUID, brand_id FK restrictOnDelete, movement_type, Kenndaten, unique brand_id+name, SoftDeletes)
- `watches` (UUID, brand_id FK, caliber_id FK nullable, model/reference/serial/stock_number, condition, status, Chrono24-Attribute [Aufzug, Geschlecht, Gehäuse/Lünette/Glas, Zifferblatt, Band/Schließe, Wasserdichtigkeit, Bandanstoß], research_data JSON [KI-Lookup], SoftDeletes)

## Models

- `App\Models\User` — zentral UND tenant (Connection-Switch); FilamentUser + HasRoles; checkPermissionTo() mit Tenant-Kontext-Guard (zentral entscheiden allein die Policies)
- `App\Models\Tenant` — stancl BaseTenant + SoftDeletes, Custom Columns (name, slug, status)
- `App\Models\Brand` — Tenant; HasUuids + SoftDeletes; hasMany Calibers/Watches (Werkhersteller wie ETA sind auch Brands)
- `App\Models\Caliber` — Tenant; HasUuids + SoftDeletes; belongsTo Brand; hasMany Watches; MovementType-Cast
- `App\Models\Watch` — Tenant; HasUuids + SoftDeletes + Scout Searchable; belongsTo Brand/Caliber; fullName()

## Filament Resources

**Central-Panel (`/admin`, Namespace `App\Filament\Central`):**
- `Tenants\TenantResource` (+ TenantForm, TenantsTable, List/Create/Edit-Pages)

**App-Panel (`/app` auf Tenant-Domains, Namespace `App\Filament\App`):**
- `Users\UserResource` (+ UserForm, UsersTable, List/Create/Edit-Pages)
- `Brands\BrandResource` (Gruppe „Stammdaten"; + BrandForm, BrandsTable, Pages, CalibersRelationManager, Papierkorb/Restore)
- `Calibers\CaliberResource` (Gruppe „Stammdaten"; + CaliberForm, CalibersTable, Pages — Form/Table werden vom RelationManager wiederverwendet, `withBrand: false`)
- `Watches\WatchResource` (Gruppe „Bestand"; + WatchForm als Tab-Layout mit KI-Referenz-Lookup [Referenznummer zuerst, ✨-Action] und abhängigem Kaliber-Select, WatchesTable mit Full-Set-Filter, Pages, Papierkorb/Restore)

**Widgets:**
- `Central\Widgets\TenantStatsWidget` (Mandanten-Kennzahlen, Dashboard)
- `App\Widgets\WatchStatsWidget` (Bestandskennzahlen, Tenant-Dashboard; canView nur mit watches.view)

## Services

- `App\Services\WatchReferenceLookupService` — KI-Recherche zu Referenznummern (Anthropic claude-opus-4-8 + Web-Suche); JSON-Parsing + Stammdaten-Matching; DTO `App\DataTransferObjects\WatchReferenceData`; Konfiguration über ANTHROPIC_API_KEY

## Actions

- `App\Actions\Tenancy\CreateTenantAction` — komplettes Provisioning
- `App\Actions\Tenancy\DeleteTenantAction` — archive() (Soft) / execute() (endgültig + DB-Löschung)

## Enums

- `App\Enums\TenantStatus` (trial/active/suspended/archived, deutsche Labels, Filament-Contracts)
- `App\Enums\UserRole` (owner/admin/employee/viewer, deutsche Labels, managementRoles())
- `App\Enums\MovementType` (manual/automatic/quartz/solar/spring_drive/smartwatch, deutsche Labels, Filament-Contracts)
- `App\Enums\WatchCondition` (new/unworn/very_good/good/fair, deutsche Labels, Filament-Contracts)
- `App\Enums\WatchStatus` (in_stock/reserved/in_service/consignment/sold, deutsche Labels, sellableStatuses())
- Chrono24-Katalog: `CaseMaterial` (19), `WatchColor` (20), `BraceletMaterial` (18), `GlassType`, `ClaspType`, `DialNumerals`, `WatchGender` — standardisierte Inserat-Attribute statt Freitext

## Jobs

- _Eigene: keine._ Genutzt werden stancl-Jobs: CreateDatabase, MigrateDatabase, SeedDatabase, DeleteDatabase

## Events

- _Eigene: keine._ stancl-Events via TenancyServiceProvider (TenantCreated-Pipeline; TenantDeleted bewusst OHNE DB-Löschung)

## Policies

- `App\Policies\TenantPolicy` — nur zentraler Kontext; forceDelete nur für archivierte
- `App\Policies\UserPolicy` — permission-basiert (users.*), Selbstlöschungs- & Owner-Hierarchie-Schutz
- `App\Policies\BrandPolicy` — master_data.*; Referenz-Schutz (Kaliber & Uhren, inkl. soft-gelöschter)
- `App\Policies\CaliberPolicy` — master_data.*; Referenz-Schutz (Uhren, inkl. soft-gelöschter)
- `App\Policies\WatchPolicy` — permission-basiert (watches.*)

## Observers

- `App\Observers\TenantObserver` — Slug-Generierung + Kollisionsauflösung (creating)

## Seeder / Factories

- `Database\Seeders\TenantDatabaseSeeder` — Rollen + Berechtigungen (users.*, roles.manage, settings.manage, master_data.*, watches.*); ruft MasterDataSeeder auf; wird bei jedem Provisioning ausgeführt
- `Database\Seeders\MasterDataSeeder` — Starter-Grundstock (20 Marken, 17 Kaliber), idempotent, respektiert mandantenseitige Löschungen
- `Database\Factories\TenantFactory`, `BrandFactory`, `CaliberFactory`, `WatchFactory` (+ UserFactory aus dem Skeleton)

## Test-Infrastruktur

- Helper `provisionTenant()` / `destroyTenant()` in `tests/Pest.php` — für alle Feature-Tests nutzbar

## Offene TODOs

- [ ] Modul 4: Medienverwaltung — Fotos/Zertifikate für Uhren (spatie/laravel-medialibrary, tenant-aware Storage!)
- [ ] Modul 4: `livewire/upload-file`-Route tenancy-fähig machen (wie Update-Route im TenancyServiceProvider — sonst 419 bei Uploads auf Tenant-Domains)
- [ ] Modul 4: Bild-URLs aus `watches.research_data` (KI-Lookup) in die Media Library übernehmen (Download-Job)
- [ ] ANTHROPIC_API_KEY in Produktion setzen; KI-Lookup ggf. per Queue-Job entkoppeln (aktuell synchron mit set_time_limit 180)
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
