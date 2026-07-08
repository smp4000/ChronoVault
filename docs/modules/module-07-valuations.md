# Modul 7 — Bewertungen & Marktwert

> Stand: 2026-07-08 · Status: ✅ Fertig

## Überblick

Marktwert-Bewertungen pro Uhr mit vollständiger Historie
(Wertentwicklung), KI-Marktrecherche und Bestandswert-Kennzahlen auf
dem Dashboard. Die in Modul 3 vorgehaltenen Schnellzugriffsfelder
(`watches.current_market_value`, `last_valuation_at`) werden jetzt gepflegt.

## Datenbank (Tenant-DB)

`valuations`: UUID, watch_id FK (restrictOnDelete), created_by FK,
source (ValuationSource), market_value + value_low/value_high (Spanne),
currency, valued_at, summary, source_urls JSON, notes, SoftDeletes,
Index (watch_id, valued_at).

## Kernmechanik

- **RecordValuationAction** (Erstellung IMMER hierüber): legt den
  Historien-Eintrag an und spiegelt den Wert an die Uhr — aber nur,
  wenn die Bewertung nicht ÄLTER ist als die aktuellste vorhandene
  (nachgetragene Historie verbiegt den aktuellen Wert nicht).
- **MarketValueLookupService** (Perplexity sonar-pro): recherchiert den
  aktuellen Gebrauchtmarkt-Preis unter Berücksichtigung von **Zustand,
  Lieferumfang (Full Set/Box/Papiere) und Baujahr**; liefert Wert,
  Preisspanne, deutsche Markteinschätzung und die tatsächlich genutzten
  Quellen (citations). Kein Anthropic-Fallback — Marktpreise brauchen
  zwingend aktuelle Web-Daten. Live verifiziert (TAG Heuer F1 x Gulf:
  4.300 €, Spanne 4.100–4.500, 11 Quellen).

## Filament

- **„Marktwert"-Schnellaktion** in der Bestandsliste: Bestätigungsdialog
  (jeder Abruf kostet API-Guthaben) → Recherche → Bewertung → Notification
  mit Wert und Differenz zum Einkaufspreis.
- **ValuationsRelationManager** („Wertentwicklung") an der Uhr:
  Historie mit Quelle-Badge, Spanne und Einschätzung; manuelle
  Bewertungen über die Action; bewusst KEINE Edit-Aktion (Historie —
  löschen + neu erfassen statt nachträglich verbiegen).
- **Marktwert-Spalte** in der Bestandsliste (sortierbar).
- **InventoryValueWidget** (Dashboard): Einkaufswert und Marktwert des
  unverkauften Bestands + Wertentwicklung in Prozent — Letztere nur
  über Uhren mit BEIDEN Werten (unbewertete verzerren sonst).

## Berechtigungen

valuations.* — Semantik wie überall. Bestandsmandanten migriert + geseedet.

## Bewusst offen

- `watchcharts_uuid` bleibt ungenutzt (externe Marktdaten-Integration
  wäre ein eigener Anbieter-Vertrag; Spalte existiert).
- Automatische periodische Neubewertung (Scheduler + Queue) — sinnvoll
  erst mit Produktions-Queue-Worker.
- Wertentwicklungs-Chart (Modul 9 Reporting).
