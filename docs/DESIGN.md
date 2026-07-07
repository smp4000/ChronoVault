# ChronoVault — Design-Referenzen

> Ergänzt die Design-Leitplanken aus CLAUDE.md (Premium-Luxus-SaaS,
> Dark Mode first für die Panels). Dieses Dokument sammelt konkrete
> Referenzen und Vorgaben des Auftraggebers.

## Öffentlicher Shop (späteres Modul)

**Vorgabe (2026-07-07): wie https://www.grimmeissen.de/de — aber in Blau.**

Analyse der Referenz-Site (Premium-Uhrenhändler):

| Aspekt | grimmeissen.de | ChronoVault-Shop |
|---|---|---|
| Grundfarben | Weiß, schwarze Schrift, subtile Grautöne | Weiß, dunkle Schrift, **Blau als Akzent-/Markenfarbe** (Buttons, Links, Preise/Hervorhebungen) |
| Typografie | Sans-Serif, klare Hierarchie (Marke prominent, Modell groß, Specs klein) | übernehmen |
| Header | Schlank: Logo, Navigation, Warenkorb; zweistufige Navigation | übernehmen |
| Produktkacheln | Grid, konsistent: Bild → Marke → Modell → Specs → Preis | übernehmen — Datenbasis: Chrono24-Attribute + photos der Watch-Entität |
| Bildsprache | Frontale, neutral beleuchtete Produktfotos, Galerie mit Thumbnails | übernehmen — geführter Foto-Upload (photo_slots) liefert genau das |
| Weißraum | großzügig, keine Überladung | übernehmen |
| Premium-Signale | Tradition („Seit 1991"), persönlicher Service, Kontakt statt anonymem Shop | pro Mandant konfigurierbar (Betriebsdaten) |

Hinweise für die Umsetzung:
- Der Shop ist die ÖFFENTLICHE Seite eines Mandanten (Tenant-Domain) —
  hell, im Kontrast zum dunklen Arbeits-Panel (/app).
- Blauton bei Umsetzung mit dem Auftraggeber festlegen (Vorschlag:
  gedecktes Marineblau als Primärfarbe + hellerer Akzent, Tailwind
  `blue`/`indigo`-Skala als Basis).
- Tailwind only (Projektregel), responsiv Desktop/Tablet/Phone.
