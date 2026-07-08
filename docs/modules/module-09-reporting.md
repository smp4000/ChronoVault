# Modul 9: Reporting & Dashboards

> Kennzahlen und Diagramme auf dem Tenant-Dashboard — Umsatz, Marge,
> Bestandszusammensetzung und Kapitalbindung auf einen Blick.

## Zweck

Das Tenant-Dashboard wird von einer reinen Bestandsanzeige (Module 3/7)
zu einem Steuerungsinstrument: Verkaufsentwicklung über 12 Monate,
Standzeiten, Bestand nach Status und die Marken, in denen das Kapital
des Betriebs steckt.

## Architektur

**Keine Business-Logik in Widgets** (Projektregel): Alle Auswertungen
liegen im `App\Services\ReportingService`, die Widgets rendern nur.

### Warum Aggregation in PHP statt SQL-Datumsfunktionen

Monats-Gruppierung wäre DB-spezifisch (`DATE_FORMAT` in MariaDB vs.
`to_char` in PostgreSQL) — Queries müssen DB-agnostisch bleiben
(ADR-001). Die Datenmengen pro Tenant (Belege eines Jahres) sind klein
genug für PHP-Gruppierung. Deutsche Monats-Labels kommen aus einer
eigenen Konstante statt `setlocale`/intl (auf Hosting-Umgebungen
unzuverlässig).

### Margen-Semantik

Marge = Verkaufspreis − Einkaufspreis der Uhr. Verkäufe OHNE
hinterlegten Einkaufspreis fließen in den Umsatz, aber NICHT in die
Marge (sonst zählten sie als 100 % Marge). Gleiches Prinzip bei der
Ø Standzeit: nur Verkäufe mit Einkaufsdatum.

## ReportingService

| Methode | Liefert |
|---|---|
| `salesByMonth(12)` | Je Monat: revenue/margin/count — lückenlose Achse, Monate ohne Verkauf = 0 |
| `salesTotals(12)` | Umsatz, Marge, Anzahl, Ø Standzeit (Einkauf → Verkauf) |
| `inventoryByStatus()` | Deutsche Status-Labels => Anzahl (nur belegte Status, Enum-Reihenfolge) |
| `topBrandsByInventoryValue(5)` | Top-Marken des unverkauften Bestands nach Einkaufswert |

Larastan-Hinweis: Date-Casts sind als string typisiert →
`getAttribute() + instanceof Carbon` (etabliertes Muster).

## Widgets (`App\Filament\App\Widgets`, Auto-Discovery)

| Widget | Typ | Sort | Sichtbar mit |
|---|---|---|---|
| WatchStatsWidget (Modul 3) | Stats | 1 | watches.view |
| InventoryValueWidget (Modul 7) | Stats | 2 | watches.view |
| **SalesStatsWidget** | Stats: Umsatz/Marge/Ø Standzeit (12 M.) | 3 | transactions.view |
| **SalesChartWidget** | Line: Umsatz + Marge je Monat, volle Breite | 4 | transactions.view |
| **InventoryByStatusWidget** | Doughnut: Bestand nach Status | 5 | watches.view |
| **TopBrandsWidget** | Bar (horizontal): Top 5 Marken nach Einkaufswert | 6 | watches.view |

Farben blau-geführt (Design-Leitplanke); Chart-Optionen über
`getOptions()` (Legende unten bzw. `indexAxis: y`).

## Tests (`tests/Feature/ReportingTest.php`, 4 Tests)

- Widget-Rendering via `Livewire::test()` mit echten Daten — das
  Dashboard lädt Widgets lazy, HTTP-assertSee auf `/app` sieht nur
  Platzhalter!
- Monats-Aggregation (Marge nur bei Einkaufspreis, 0-Buckets)
- Totals inkl. Ø Standzeit (40+20 Tage → 30)
- Status-Gruppierung + Marken-Ranking (Verkauft zählt nicht ins Kapital)

## Mögliche Erweiterungen

- Zeitraum-Filter am Chart (`getFilters()`: 3/6/12 Monate, Jahr)
- CSV-/PDF-Export (Bestandsliste für Versicherung, Verkaufsjournal)
- Service-Kosten- und Auktions-Erlös-Auswertungen
- Vergleich zum Vorjahreszeitraum
