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

## ADR-006: PHP 8.2 als lokale Basis (2026-07-07)

**Kontext:** XAMPP liefert PHP 8.2.12; „latest PHP" wäre 8.4.

**Entscheidung:** Code targetet PHP 8.2+ (Laravel-12-Minimum) und verwendet
keine 8.3/8.4-only-Features, bis die lokale Umgebung aktualisiert ist.
