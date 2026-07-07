# Modul 3 — Kernmodul: Uhren (Watches)

> Stand: 2026-07-07 · Status: ✅ Fertig

## Überblick

Die zentrale Bestandstabelle der Plattform: jede physische Uhr eines
Betriebs, verknüpft mit den Stammdaten aus Modul 2 (Marke Pflicht,
Kaliber optional). Neue Navigation-Gruppe **„Bestand"** im App-Panel —
alphabetisch vor „Stammdaten" und „Verwaltung", denn hier arbeiten die
Nutzer täglich.

- **Tenant-Tabelle** `watches` (UUID-PK, SoftDeletes)
- **Enums** `WatchCondition` (Zustand) und `WatchStatus` (Bestandsstatus)
- **Berechtigungen** `watches.*` (gleiche Semantik wie `master_data.*`)
- **Scout-Volltextsuche** (database-Driver, ADR-003)
- **Dashboard-Widget** `WatchStatsWidget` (Bestandskennzahlen)

## Datenbank-Design (Tenant-DB)

| Bereich | Spalten |
|---|---|
| Referenzen | `brand_id` (FK, restrictOnDelete), `caliber_id` (FK, **nullable**, restrictOnDelete) |
| Identifikation | `model_name`, `reference_number`, `serial_number` (Index), `stock_number` (**unique**, nullable), `production_year` |
| Zustand/Status | `condition`, `status` (Index), `has_box`, `has_papers` |
| Ausstattung | `case_material`, `case_diameter_mm`, `dial_color`, `bracelet_material` |
| Sonstiges | `notes` (intern), Timestamps, SoftDeletes |

Design-Entscheidungen:

- **`caliber_id` nullable**: Werk oft unbekannt/irrelevant (Quarz-Modeuhren).
- **`serial_number` bewusst NICHT unique**: Graumarkt-Realität — unbekannte
  oder unvollständig erfasste Seriennummern; nur indexiert.
- **`stock_number` unique**: interne SKU des Betriebs; nullable (mehrere
  NULLs kollidieren weder in MariaDB noch PostgreSQL).
- **KEINE Preisspalten**: Einkauf/Verkauf/Historie werden eigene Tabellen
  (Modul 5) — eine Uhr kann mehrfach den Besitzer wechseln.

## Enums

- `WatchCondition`: new/unworn/very_good/good/fair → Neu/Ungetragen/
  Sehr gut/Gut/Getragen
- `WatchStatus`: in_stock/reserved/in_service/consignment/sold → An Lager/
  Reserviert/Im Service/Kommission/Verkauft; `sellableStatuses()` liefert
  die verkäuflichen Status (Lager-Kennzahlen, Verkaufslogik in Modul 5)

## Rollen & Berechtigungen

Identische Semantik wie Stammdaten: `watches.view` für alle Rollen,
`create`/`update` auch für Mitarbeiter, `delete` nur Owner/Admin.
Verkaufte Uhren bleiben bewusst editierbar (Korrekturen) — die
Verkaufslogik inkl. Sperren folgt in Modul 5.

## Referenz-Schutz (Erweiterung Modul 2)

`BrandPolicy` und `CaliberPolicy` verweigern delete/forceDelete jetzt
auch bei referenzierenden **Uhren** — inkl. soft-gelöschter
(`withTrashed`, die FK-Referenz existiert physisch weiter). DB-seitig
sichert `restrictOnDelete` als letzte Verteidigungslinie.

## Filament

- **WatchForm**: Abhängiges Kaliber-Select — Marken-Feld ist `live()`,
  Markenwechsel setzt das Kaliber zurück; Kaliber-Optionen sind auf die
  gewählte Marke gefiltert. Selects zeigen nur aktive Stammdaten plus
  den aktuell zugewiesenen Wert (Bestandsschutz).
- **WatchesTable**: Status-/Zustands-Badges, Referenz als description
  unter dem Modell, Full-Set-Filter (Box UND Papiere), Multi-Select-
  Filter für Status/Zustand, Papierkorb. Default-Sortierung: neueste zuerst.
- **WatchStatsWidget** (Dashboard): Verkaufsbereit (An Lager + Kommission),
  Reserviert, Im Service, Verkauft — eine Aggregat-Query;
  `canView()` nur mit `watches.view`.
- **Global Search** über Modell, Referenz-, Serien-, Bestandsnummer und
  Marke; Treffertitel via `Watch::fullName()` („Rolex Submariner Date
  (126610LN)").

## Scout (ADR-003)

- `SCOUT_DRIVER=database` jetzt explizit in `.env`/`.env.example`
  (vorher fiel Scout auf den collection-Driver zurück).
- `Watch` nutzt `Searchable`; `toSearchableArray()`: model_name,
  reference_number, serial_number, stock_number. Database-Driver =
  LIKE-Suche auf der Tenant-Connection — tenant-safe ohne Zusatzaufwand.
- Meilisearch-Umstieg (Produktion) bleibt reiner Driver-Wechsel.

## Bekannte Stolperfallen (dokumentiert für die Zukunft)

- **Kein Auto-Seed von Uhren**: watches sind Bewegungs-/Geschäftsdaten,
  kein Stammdaten-Grundstock — Factories nur in Tests verwenden.
- **`$table = 'watches'` explizit** im Model gesetzt (Laravel würde es
  korrekt raten, die Kernentität verdient aber keine Implizitheit).

## Mögliche Erweiterungen

- Fotos/Zertifikate (Modul 4), Kauf/Verkauf & Preise (Modul 5),
  Servicehistorie (Modul 6)
- Status InAuction (Modul 8), OnApproval (Ansichtssendung)
- Duplizieren-Action für ähnliche Uhren (Serienerfassung)
