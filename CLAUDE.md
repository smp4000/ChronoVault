# ChronoVault — Entwicklungsrichtlinien

ChronoVault ist eine Enterprise-SaaS-Plattform für Uhrensammler, Händler,
Juweliere und Auktionshäuser. Laravel 12 + Filament, Multi-Tenant (stancl/tenancy).

## Pflicht bei jeder Session

1. **Zuerst `docs/PROJECT_STATUS.md` lesen** — dort stehen Module, Tabellen,
   Models, Resources, Services, Actions, Enums, Jobs, Events, Policies,
   Observers, TODOs. Der Statusblock am Antwortanfang wird daraus generiert.
2. **Nach jedem abgeschlossenen Schritt `docs/PROJECT_STATUS.md` aktualisieren.**
3. Architektur-Entscheidungen in `docs/DECISIONS.md` (ADR) festhalten.
4. Es gilt: **alles automatisch, ohne Rückfragen** umsetzen.

## Eiserne Regeln

- **Quellcode 100 % Englisch** (Tabellen, Spalten, Models, Methoden, Variablen, Routes).
- **UI 100 % Deutsch** (Filament-Labels, Meldungen, Notifications, E-Mails).
- **Code-Dokumentation auf Deutsch**: Jede Datei beginnt mit einem Docblock
  (Zweck, Verantwortlichkeiten, Abhängigkeiten, Nutzung, Erweiterungen).
  Kommentare erklären WARUM, nicht nur WAS.
- **Keine Business-Logik in Filament Resources** → Services, Actions, DTOs,
  Events, Listeners, Policies, Observers.
- Bestehende Strukturen nie neu erstellen oder umbenennen — nur erweitern.
- Migrationen DB-agnostisch (lokal MariaDB, Produktion PostgreSQL — ADR-001).
- UUIDs für öffentliche/domänenrelevante Entitäten, Soft Deletes für Domänendaten.
- Jobs immer queue-fähig schreiben (lokal database-Queue, Produktion Redis/Horizon — ADR-002).

## Modul-Checkliste (jedes Domänenmodul)

Datenbank-Design → Migration → Model → Enum(s) → Policy → Observer → Service →
Action(s) → Form Request → Filament Resource (+ Widgets, Relation Managers) →
Factory → Seeder → Pest-Tests → Doku (`docs/modules/<modul>.md`) → Status-Update.

## Umgebung (lokal)

- Windows 11, XAMPP: PHP 8.2.12, MariaDB (DB `chronovault`, User `root`, kein Passwort)
- MariaDB starten: `C:\xampp\mysql\bin\mysqld.exe` (kein Windows-Service)
- Node 22 / Vite; Dev-Server: `php artisan serve` (Port 8000)
- Kein Redis, kein PostgreSQL, kein Meilisearch lokal (siehe ADRs 001–003)

## Befehle

- Tests: `php artisan test` (Pest)
- Statische Analyse: `vendor/bin/phpstan analyse`
- Code-Style: `vendor/bin/pint`
- Frontend-Build: `npm run build` / `npm run dev`

## Design-Leitplanken

Premium-Luxus-SaaS (Apple/Stripe/Linear/Vercel): Dark Mode first, elegante
Typografie, abgerundete Ecken, dezente Micro-Animationen, schöne Empty- und
Loading-States. Tailwind only, kein Bootstrap. Responsiv (Desktop/Tablet/Phone).
