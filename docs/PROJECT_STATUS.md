# ChronoVault — Projektstatus

> **Diese Datei ist die Single Source of Truth für den Projektstatus.**
> Sie wird nach JEDEM abgeschlossenen Arbeitsschritt aktualisiert und dient als
> Statusblock-Quelle am Anfang jeder Entwicklungs-Session.
>
> Letzte Aktualisierung: 2026-07-07 (Modul 0 — Foundation)

---

## Aktueller Stand

**Modul 0 (Foundation) abgeschlossen.** Laravel 12 + Filament + Basispakete
installiert, Umgebung konfiguriert, Admin-Panel eingerichtet, Doku-Struktur steht.

**Nächster Schritt:** Modul 1 — Tenancy & Benutzer-/Rollenverwaltung.

---

## Module

| # | Modul | Status |
|---|-------|--------|
| 0 | Foundation (Scaffold, Pakete, Panel, Doku) | ✅ Fertig |
| 1 | Tenancy & Benutzer-/Rollenverwaltung | ⬜ Offen |
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

- Standard-Laravel: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`
- Noch keine Domänentabellen (folgen ab Modul 1)

## Models

- `App\Models\User` (Standard, wird in Modul 1 erweitert)

## Filament Resources

- _Noch keine_ (Admin-Panel-Provider vorhanden: `App\Providers\Filament\AdminPanelProvider`)

## Services

- _Noch keine_

## Actions

- _Noch keine_

## Enums

- _Noch keine_

## Jobs

- _Noch keine_

## Events

- _Noch keine_

## Policies

- _Noch keine_

## Observers

- _Noch keine_

## Offene TODOs

- [ ] Modul 1: stancl/tenancy konfigurieren (Tenant-Model, Domains, zentrale vs. Tenant-Migrationen)
- [ ] Modul 1: spatie/laravel-permission einrichten (Rollen: Admin, Dealer, Collector, Auctioneer)
- [ ] Redis lokal nicht verfügbar → Horizon erst in Produktion (Linux) möglich (benötigt `pcntl`/`posix`, existiert unter Windows nicht); lokal `database`-Queue
- [ ] Meilisearch lokal installieren, Scout von `database`- auf Meilisearch-Driver umstellen
- [ ] PostgreSQL als Produktions-DB dokumentieren; lokale Entwicklung läuft auf MariaDB (XAMPP) → Migrationen DB-agnostisch halten
- [ ] Laravel Pulse konfigurieren (Dashboard, Storage)
- [ ] Telescope nur in `local` registrieren
- [ ] Pest-Testsuite aufsetzen (PHPUnit ersetzen), PHPStan-Baseline (larastan) einrichten
- [ ] Deutsches Sprachpaket (`laravel-lang`) für Framework-Validierungsmeldungen erwägen
- [ ] Filament-Theme: Dark-Mode-first, Premium-Look (eigenes Theme-CSS)

## Mögliche zukünftige Verbesserungen

- Kunden-Portal (separates Filament-Panel oder Livewire-Frontend) für Endkunden von Händlern
- Öffentliche Watch-Marktplatz-Ansicht mit SEO-Landingpages
- KI-gestützte Preisbewertung (Marktdaten-Anbindung, Chrono24-Preisvergleich)
- Mobile App über API (Sanctum-Tokens bereits eingeplant)
- Mehrsprachigkeit der UI über spatie/laravel-translatable + Sprachumschalter (aktuell: Deutsch fix)
- Audit-Export (Aktivitätslog als PDF/CSV für Versicherungen)
- Barcode-/QR-Etiketten für physische Uhrenlagerung
- Webhooks für Drittsysteme (Warenwirtschaft, Buchhaltung)
