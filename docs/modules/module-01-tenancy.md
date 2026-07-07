# Modul 1 — Tenancy & Benutzer-/Rollenverwaltung

> Stand: 2026-07-07 · Status: ✅ Fertig

## Überblick

ChronoVault ist ab diesem Modul eine echte Multi-Tenant-SaaS:

- **Multi-Database-Tenancy** (stancl/tenancy, ADR-007): Jeder Mandant
  (Händler, Juwelier, Auktionshaus) erhält eine **eigene Datenbank**
  `cv_tenant_<uuid>`. Harte Datenisolation — ein Verkaufsargument für die
  Luxusgüter-Branche.
- **Domain-Identifikation**: Mandanten laufen unter `{slug}.localhost`
  (lokal) bzw. `{slug}.<TENANT_DOMAIN_SUFFIX>` (Produktion). Custom-Domains
  sind über die `domains`-Tabelle bereits vorbereitet.
- **Zwei Filament-Panels**:
  - `/admin` (zentral, Namespace `App\Filament\Central`) — Plattform-Team
    verwaltet Mandanten.
  - `/app` (Tenant-Domains, Namespace `App\Filament\App`) — die eigentliche
    Anwendung der Betriebe. Auf zentralen Domains blockiert (404).
- **Rollen pro Mandant** (spatie/laravel-permission in der Tenant-DB):
  Inhaber, Administrator, Mitarbeiter, Betrachter (`App\Enums\UserRole`).

## Datenbank-Design

### Zentrale DB

| Tabelle | Zweck |
|---|---|
| `tenants` | `id` (UUID), `name`, `slug` (unique), `status`, `data` (JSON), Timestamps, **SoftDeletes** |
| `domains` | stancl-Standard: `domain` (unique) → `tenant_id` |
| `users` | Nur Plattform-Betreiber (Super-Admins) |

### Tenant-DB (Migrationen in `database/migrations/tenant/`)

| Tabelle | Zweck |
|---|---|
| `users`, `password_reset_tokens`, `sessions` | Mitarbeiter des Betriebs; Sessions bewusst IN der Tenant-DB (Isolation) |
| `cache`, `cache_locks` | Tenant-lokaler Cache (database-Driver folgt der Default-Connection) |
| `roles`, `permissions`, `model_has_roles`, … | spatie-Permission-Tabellen pro Mandant |

Jobs/Queue bleiben **zentral** (`DB_QUEUE_CONNECTION=mysql`): ein Worker für
alle Mandanten; stancl QueueTenancyBootstrapper hängt die tenant_id an die
Job-Payload.

## Provisioning-Ablauf (CreateTenantAction)

1. `Tenant::create()` → stancl-Pipeline: **CreateDatabase → MigrateDatabase →
   SeedDatabase** (TenantDatabaseSeeder: 4 Rollen + 6 Basis-Berechtigungen)
2. Domain `{slug}.{suffix}` registrieren
3. Owner-Benutzer in der Tenant-DB anlegen + Rolle `owner` zuweisen

Slug-Generierung inkl. Kollisionsauflösung: `TenantObserver` (creating).

## Zweistufiges Löschkonzept (DeleteTenantAction)

| Stufe | Methode | Wirkung |
|---|---|---|
| Archivieren | `archive()` | Soft Delete + Status `archived`. Tenant-DB bleibt erhalten. Reversibel. |
| Endgültig | `execute()` | `DeleteDatabase`-Job + Domains löschen + `forceDelete()`. **Unwiderruflich.** |

Die automatische DB-Löschung wurde aus der stancl-Event-Pipeline **entfernt**
(TenancyServiceProvider) — ein Soft Delete hätte sonst die DB physisch gelöscht.

## Sicherheits-Invarianten

- `User::canAccessPanel()`: admin-Panel nur ohne Tenant-Kontext; app-Panel
  nur mit aktivem (nicht gesperrtem) Mandanten.
- `TenantPolicy`: Mandantenverwaltung nur im zentralen Kontext;
  forceDelete nur für bereits archivierte Tenants.
- `UserPolicy`: permission-basiert (`users.*`); Selbstlöschungs-Schutz;
  Owner-Accounts nur durch Owner veränderbar. Keine Bulk-Löschung (würde
  Policy-Checks pro Datensatz umgehen).
- Permission-Cache auf `array`-Store (ADR-008): kein Cache-Bleed zwischen
  Mandanten.

## Bekannte Stolperfallen (dokumentiert für die Zukunft)

- **Zentrale `/`-Routen müssen an `central_domains` gebunden werden**
  (`Route::domain(...)` in `routes/web.php`), sonst überschreibt die
  Tenant-`/`-Route sie (gleiche URI!).
- `asset_helper_tenancy` ist deaktiviert — Filament/Vite-Assets sind global.
  Tenant-Dateien später explizit über `tenant_asset()`.
- Neue Domänenmodule: Migrationen nach `database/migrations/tenant/`,
  Berechtigungen im `TenantDatabaseSeeder` ergänzen, danach
  `php artisan tenants:migrate` + `tenants:seed` für Bestandsmandanten.

## Demo-Zugang (lokal)

- Zentral: `http://localhost:8000/admin` — monor5000@gmail.com
- Tenant: `http://demo-uhrenhandel-gmbh.localhost:8000/app` —
  demo@chronovault.test / DemoVault!2026

## Mögliche Erweiterungen

- Onboarding-Wizard + Self-Service-Registrierung (statt Anlage durch Plattform-Team)
- Willkommens-E-Mail mit Passwort-Reset-Link statt Initialpasswort
- User Impersonation (stancl Feature) für Support-Zwecke
- Backup vor endgültiger Löschung; Karenzzeit für archivierte Mandanten
- Eigene Rollen pro Mandant über RoleResource (`roles.manage`-Berechtigung existiert bereits)
