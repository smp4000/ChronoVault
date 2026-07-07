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

## Chrono24-Attributkatalog (Nachtrag)

Das Uhren-Formular bildet den Chrono24-Inserat-Katalog ab —
**standardisierte Enums statt Freitext** (Basis für Filter, Auswertungen
und den späteren Inserat-Export):

| Attribut | Spalte | Enum |
|---|---|---|
| Aufzug | `movement_type` | `MovementType` (+ Smartwatch) |
| Geschlecht | `gender` | `WatchGender` |
| Baujahr ungefähr | `is_production_year_approximate` | bool |
| Material Gehäuse/Lünette/Schließe | `case_material`, `bezel_material`, `clasp_material` | `CaseMaterial` (19 Werte) |
| Farben Zifferblatt/Lünette/Armband | `dial_color`, `bezel_color`, `bracelet_color` | `WatchColor` (20 Werte) |
| Glas | `glass_type` | `GlassType` |
| Zifferblatt-Zahlen | `dial_numerals` | `DialNumerals` |
| Material Armband | `bracelet_material` | `BraceletMaterial` (18 Werte) |
| Schließe | `clasp_type` | `ClaspType` |
| Durchmesser 2D | `case_diameter_mm` × `case_height_mm` | decimal |
| Wasserdichtigkeit | `water_resistance_bar` | tinyint |
| Bandanstoß | `lug_width_mm` | tinyint |

Die früheren Freitext-Spalten (`case_material`, `dial_color`,
`bracelet_material`) wurden per Migration auf Enum-Codes konvertiert
(bekannte deutsche Begriffe gemappt, Unbekanntes genullt — es gab nur
Dev-Daten). **Inserat-spezifisches** (Titel-Ergänzung, Preis, Standort,
Versand) gehört bewusst NICHT hierher — das ist Modul 5 (Verkauf/Inserat);
die Uhr ist der Lagerbestand.

## KI-Referenz-Lookup (Nachtrag)

Das Uhren-Formular ist ein **Tab-Layout** (Uhr → Zustand & Status →
Gehäuse & Ausstattung → Notizen); die **Referenznummer steht an erster
Stelle**. Ihre Suffix-Action (✨) startet den
`WatchReferenceLookupService`:

- **Anthropic Claude API** (`claude-opus-4-8`) mit Web-Suche
  (`web_search_20260209`) recherchiert die Referenz und liefert JSON
  (Marke, Modell, Kaliber, Baujahr, Gehäuse, Zifferblatt, Band,
  Kurzbeschreibung, Bild-/Quellen-URLs).
- **Kein Structured-Output-Format möglich** (Web-Suche erzeugt Citations,
  die damit inkompatibel sind) → striktes JSON per Prompt + defensives
  Parsing (`parseResponseJson`, testbar ohne API).
- **Stammdaten-Matching**: Marke case-insensitiv exakt, Kaliber mit
  Präfix-Toleranz („Kaliber 3235" ↔ „3235"). Es werden NIE automatisch
  Stammdaten angelegt. Ohne Treffer → Hinweis in der Notification.
- **`watches.research_data`** (JSON): Beschreibung + Bild-/Quellen-URLs
  des Lookups. **Modul 4 lädt daraus die Fotos in die Media Library.**
- Konfiguration: `ANTHROPIC_API_KEY` in `.env`
  (`config/services.php → anthropic`). Ohne Key: deutsche Fehlermeldung
  als Notification, Feature ansonsten inert.
- `set_time_limit(180)` im Service — Web-Recherche überschreitet sonst
  das PHP-Limit (XAMPP: 30 s); `pause_turn` der Server-Tool-Schleife
  wird bis zu 3× fortgesetzt.

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
