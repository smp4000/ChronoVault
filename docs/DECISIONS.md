# ChronoVault — Architektur-Entscheidungen (ADR-Log)

> Jede nicht-triviale Architektur-Entscheidung wird hier festgehalten:
> Kontext → Entscheidung → Begründung → Konsequenzen.
> So bleibt auch nach Monaten nachvollziehbar, WARUM etwas so gebaut wurde.

---

## ADR-001: MariaDB lokal, PostgreSQL als Produktionsziel (2026-07-07)

**Kontext:** Der Ziel-Stack sieht PostgreSQL vor. Auf der lokalen
Entwicklungsmaschine (Windows/XAMPP) ist PostgreSQL nicht installiert und
PHP hat kein `pdo_pgsql`; MariaDB läuft bereits.

**Entscheidung:** Lokale Entwicklung auf MariaDB (`chronovault`-Datenbank),
Produktion auf PostgreSQL.

**Begründung:** Kein Setup-Blocker; Laravel-Migrationen abstrahieren die DB.

**Konsequenzen:** Migrationen müssen DB-agnostisch bleiben — keine Raw-SQL-
Statements mit MySQL-Spezifika, keine DB-spezifischen Spaltentypen. JSON-
Spalten, UUIDs und Indizes nur über den Schema-Builder definieren.

## ADR-002: Kein Horizon unter Windows (2026-07-07)

**Kontext:** Laravel Horizon benötigt die PHP-Extensions `pcntl` und `posix`.
Beide existieren unter Windows prinzipiell nicht.

**Entscheidung:** Horizon wird NICHT lokal installiert. Lokal läuft die Queue
über den `database`-Driver mit `php artisan queue:work`. Horizon wird erst
beim Produktions-Deployment (Linux + Redis) eingeführt.

**Konsequenzen:** Jobs werden von Anfang an queue-fähig geschrieben
(`ShouldQueue`), damit der spätere Wechsel auf Redis/Horizon nur
Konfiguration ist, kein Code-Umbau.

## ADR-003: Scout mit database-Driver lokal, Meilisearch in Produktion (2026-07-07)

**Kontext:** Meilisearch ist lokal nicht installiert.

**Entscheidung:** `SCOUT_DRIVER=database` lokal; Searchable-Traits werden ab
Modul 3 (Watches) eingebaut, sodass der Umstieg auf Meilisearch nur ein
Driver-Wechsel ist.

## ADR-004: Kein Laravel Breeze — Filament übernimmt die Authentifizierung (2026-07-07)

**Kontext:** Der Ziel-Stack listet Breeze. ChronoVault ist aber eine
Filament-first-Anwendung; Filament bringt Login, Passwort-Reset,
E-Mail-Verifizierung und Profilseite mit.

**Entscheidung:** Breeze wird nicht installiert. Auth läuft über das
Filament-Panel. Sollte später ein separates Kunden-Frontend entstehen,
wird dessen Auth dann gezielt entschieden (zweites Panel vs. Breeze).

**Begründung:** Breeze würde parallele Auth-Routen/Views erzeugen, die
niemand nutzt — Verstoß gegen KISS und YAGNI.

## ADR-005: Multi-Tenancy mit stancl/tenancy, Aufbau in Modul 1 (2026-07-07)

**Kontext:** SaaS für viele Händler/Auktionshäuser → Mandantentrennung ist
fundamental und lässt sich nachträglich nur teuer einbauen.

**Entscheidung:** stancl/tenancy ist ab Modul 0 installiert; die konkrete
Konfiguration (Tenant-Model, Domain-Routing, Migrations-Trennung
central/tenant) ist der Kern von Modul 1 — VOR allen Domänenmodulen.

**Konsequenzen:** Alle Domänentabellen ab Modul 2 entstehen als
Tenant-Migrationen. Zentrale Tabellen: Tenants, Domains, zentrale User.

## ADR-007: Multi-Database-Tenancy mit Domain-Identifikation (2026-07-07)

**Kontext:** ChronoVault bedient Händler/Juweliere/Auktionshäuser mit
hochsensiblen Daten (Einkaufspreise, Kundenlisten, Versicherungswerte).

**Entscheidung:** stancl/tenancy im Multi-Database-Modus: eine physische
Datenbank pro Mandant (`cv_tenant_<uuid>`), Identifikation über die volle
Domain (`InitializeTenancyByDomain`, Tabelle `domains`).

**Begründung:** Harte Datenisolation (kein vergessener `tenant_id`-Scope
kann je Daten leaken), einfaches Einzel-Mandanten-Backup/-Restore/-Export
(DSGVO), Verkaufsargument im Enterprise-Segment. Volle Domain statt
Subdomain-Matching, damit Custom-Domains später ohne Umbau möglich sind.

**Konsequenzen:** Alle Domänen-Migrationen ab Modul 2 nach
`database/migrations/tenant/`; zentrale `/`-Routen müssen an
`central_domains` gebunden werden; Jobs laufen über die zentrale Queue
(`DB_QUEUE_CONNECTION`), stancl macht sie tenant-aware. Zwei Panels:
`App\Filament\Central` (admin) und `App\Filament\App` (app).

## ADR-008: Spatie-Permission-Cache auf array-Store (2026-07-07)

**Kontext:** spatie/laravel-permission cached Rollen/Berechtigungen unter
einem GLOBALEN Cache-Key. Bei Multi-DB-Tenancy würden sich die Rollen
verschiedener Mandanten über einen geteilten persistenten Cache vermischen.

**Entscheidung:** `config/permission.php → cache.store = 'array'`
(nur Request-Lebensdauer).

**Begründung:** Korrektheit vor Mikro-Optimierung; die Rollen-Query pro
Request ist vernachlässigbar. Alternative (verworfen): Cache-Key pro Tenant
patchen — fragil gegenüber Paket-Updates.

**Konsequenzen:** Bei Produktions-Redis kann auf einen persistenten Store
mit Tenant-Präfix (CacheTenancyBootstrapper) gewechselt werden.

## ADR-009: Stammdaten pro Tenant statt zentralem Katalog (2026-07-07)

**Kontext:** Marken & Kaliber (Modul 2) könnten zentral gepflegt und allen
Mandanten bereitgestellt werden (ein Katalog, keine Duplikate) — oder pro
Tenant-Datenbank liegen.

**Entscheidung:** Stammdaten liegen in der TENANT-Datenbank. Jeder Mandant
erhält beim Provisioning einen kuratierten Grundstock (MasterDataSeeder,
idempotent) und kann ihn frei erweitern, ändern oder löschen.

**Begründung:** Mandanten brauchen eigene Einträge (Kleinserien-Marken,
Eigenmarken, exotische Kaliber) ohne Freigabeprozess. Ein zentraler Katalog
würde Cross-DB-Referenzen (zentrale brand_id in Tenant-Uhren) erfordern —
das bricht die harte Datenisolation (ADR-007) und verhindert sauberes
Einzel-Mandanten-Backup/-Restore.

**Konsequenzen:** Grundstock-Updates erreichen Bestandsmandanten nur über
`tenants:seed` (Seeder respektiert mandantenseitige Löschungen via
withTrashed-Lookup). Redundante Datenhaltung wird bewusst in Kauf genommen.

## ADR-006: PHP 8.2 als lokale Basis (2026-07-07)

**Kontext:** XAMPP liefert PHP 8.2.12; „latest PHP" wäre 8.4.

**Entscheidung:** Code targetet PHP 8.2+ (Laravel-12-Minimum) und verwendet
keine 8.3/8.4-only-Features, bis die lokale Umgebung aktualisiert ist.
