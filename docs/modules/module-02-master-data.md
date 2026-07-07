# Modul 2 — Stammdaten: Marken (Brands) & Kaliber (Calibers)

> Stand: 2026-07-07 · Status: ✅ Fertig

## Überblick

Erstes Domänenmodul auf Tenant-Ebene: Jeder Mandant pflegt seinen eigenen
Katalog aus Uhrenmarken und Uhrwerken/Kalibern (ADR-009 — kein zentraler
Katalog). Die Stammdaten sind die Referenzbasis für Modul 3 (Watches).

- **Tenant-Tabellen** `brands` und `calibers` (UUID-PKs, SoftDeletes)
- **Starter-Grundstock**: 20 kuratierte Marken + 17 bekannte Kaliber werden
  bei jedem Provisioning geseedet (`MasterDataSeeder`, idempotent)
- **Zwei Filament-Resources** im App-Panel (Gruppe „Stammdaten") plus
  `CalibersRelationManager` an der Marke — Formular/Tabelle der Kaliber
  werden wiederverwendet (`withBrand: false` blendet Hersteller-Feld aus)
- **Berechtigungen** `master_data.*` (view/create/update/delete)

## Datenbank-Design (Tenant-DB)

| Tabelle | Spalten (Kern) |
|---|---|
| `brands` | `id` (UUID), `name` (unique), `country`, `founded_year`, `website`, `description`, `is_active`, Timestamps, SoftDeletes |
| `calibers` | `id` (UUID), `brand_id` (FK, **restrictOnDelete**), `name`, `movement_type`, `power_reserve_hours`, `frequency_vph`, `jewels`, `diameter_mm`, `description`, `is_active`, Timestamps, SoftDeletes, **unique(brand_id, name)** |

Design-Entscheidungen:

- **Werkhersteller (ETA, Sellita) sind Brands** — kein eigener Entitätstyp
  (KISS; `is_active` + spätere Auswertungen unterscheiden ausreichend).
- **Kalibernamen nur pro Marke eindeutig** („2824-2" könnte bei zwei
  Herstellern existieren) — Unique-Index auf `(brand_id, name)`.
- **Spaltennamen tragen Einheiten** (`power_reserve_hours`, `frequency_vph`,
  `diameter_mm`) — selbstdokumentierend, keine Einheiten-Verwirrung.

## Rollen & Berechtigungen

| Berechtigung | Owner | Admin | Employee | Viewer |
|---|---|---|---|---|
| `master_data.view` | ✅ | ✅ | ✅ | ✅ |
| `master_data.create` | ✅ | ✅ | ✅ | — |
| `master_data.update` | ✅ | ✅ | ✅ | — |
| `master_data.delete` | ✅ | ✅ | — | — |

Stammdaten-Pflege ist operatives Arbeiten (inkl. Mitarbeiter); Löschen
bleibt der Verwaltung vorbehalten. Für Bestandsmandanten: `php artisan
tenants:migrate` + `tenants:seed` (Seeder ist idempotent).

## Schutzregeln

- **BrandPolicy**: Marken mit Kalibern sind weder lösch- noch
  force-löschbar (Referenz-Schutz). Alternative für „ausblenden":
  `is_active = false` — inaktive Marken/Kaliber erscheinen nicht in
  Auswahlfeldern neuer Datensätze.
- **DB-Ebene**: `calibers.brand_id` mit `restrictOnDelete` als letzte
  Verteidigungslinie gegen hartes Löschen.
- **SoftDeletes + Papierkorb**: Beide Tabellen haben TrashedFilter,
  Restore- und ForceDelete-Actions (Policies: `restore`/`forceDelete`
  → `master_data.delete`).
- **Keine Bulk-Löschaktionen** (Konsistenz mit Modul 1: Policy-Checks
  pro Datensatz).

## Starter-Grundstock (MasterDataSeeder)

- Wird vom `TenantDatabaseSeeder` aufgerufen → läuft bei Provisioning
  UND `tenants:seed`.
- **Idempotent**: `firstOrCreate` über `name` bzw. `(brand_id, name)`,
  jeweils `withTrashed` im Lookup — vom Mandanten GELÖSCHTE
  Grundstock-Einträge werden NICHT wiederbelebt (und es entsteht kein
  Unique-Konflikt mit soft-gelöschten Zeilen).
- Inhalt: 20 Marken (Rolex bis Sellita) mit Land/Gründungsjahr;
  17 Kaliber mit Werktyp und technischen Kenndaten.

## Formular-Besonderheiten

- **Kaliber-Unique-Validierung kontextabhängig**: `brand_id` kommt aus dem
  Formularfeld (CaliberResource) ODER vom Owner-Record
  (CalibersRelationManager) — siehe `CaliberForm::configure()`.
- **Hersteller-Auswahl** zeigt nur aktive Marken, plus die aktuell
  zugewiesene Marke beim Bearbeiten (sonst „verlöre" ein Kaliber einer
  inaktiven Marke seinen Hersteller im Formular).
- **Marken-Name-Unique** schließt soft-gelöschte Marken ein (der
  DB-Index kennt kein SoftDelete) — Wiederherstellung über den
  Papierkorb-Filter statt Neuanlage.

## Bekannte Stolperfallen (dokumentiert für die Zukunft)

- **TrashedFilter braucht Scope-Entfernung**: Resources entfernen den
  `SoftDeletingScope` in `getEloquentQuery()`; der RelationManager
  zusätzlich via `modifyQueryUsing` an der Tabelle (er baut seine Query
  aus der Beziehung, nicht aus der Resource).
- **Test-Helper liegen in `tests/Pest.php`** (`provisionTenant()`,
  `destroyTenant()`) — PHP-Funktionen dürfen nur einmal definiert werden;
  neue Feature-Tests nutzen sie einfach mit.
- **MovementType-Werte nie umbenennen** — sie sind in Tenant-Datenbanken
  persistiert (gleiches Prinzip wie UserRole/TenantStatus).

## Mögliche Erweiterungen

- ~~Referenz-Schutz in CaliberPolicy~~ → in Modul 3 umgesetzt (inkl. BrandPolicy für Uhren)
- Markenlogos über die Medienverwaltung (Modul 4)
- Scout-Searchable für Marken/Kaliber (mit Modul 3, ADR-003)
- Weitere Werktypen (Kinetic, Mecaquartz) bei Bedarf
- Import/Export des Markenkatalogs (CSV)
