# Modul: Öffentlicher Shop (Schaufenster)

> Öffentliche Storefront auf der Tenant-Domain — Design-Referenz
> [docs/DESIGN.md](../DESIGN.md): grimmeissen.de-Stil, **Blau** als Akzentfarbe,
> weiße Basis, viel Weißraum, Tailwind only.

## Zweck

Jeder Händler erhält auf seiner Domain (z. B. `welle.localhost`) ein
öffentliches Schaufenster: Die Wurzel `/` zeigt die Kollektion, `/uhren/{id}`
die Detailseite. Das interne Panel bleibt unverändert unter `/app`.

Veröffentlichung ist **Opt-in pro Uhr** (Kommissions-/Kundenware bleibt sonst
intern) und zusätzlich an den Status gekoppelt: Nur verkäufliche Uhren
(`WatchStatus::sellableStatuses()` = An Lager, Kommission) erscheinen —
verkaufte oder im Service befindliche verschwinden automatisch, ohne dass
der Händler den Haken entfernen muss.

## Datenbasis

Migration `2026_07_08_060000_add_shop_fields_to_watches_table.php`:

| Spalte | Typ | Bedeutung |
|---|---|---|
| `is_published` | boolean, default false, Index | Uhr im Shop sichtbar (Opt-in) |
| `asking_price` | decimal(12,2), nullable | Öffentlicher Verkaufspreis; leer = „Preis auf Anfrage" |

`asking_price` ist bewusst getrennt vom internen `purchase_price` und vom
recherchierten `current_market_value` — der Händler entscheidet den
Angebotspreis selbst.

## Bausteine

- **Watch-Model** — Scope `publishedInShop()` (veröffentlicht + verkäuflich;
  SoftDeletes filtern gelöschte automatisch), Helper `formattedAskingPrice()`
  („12.500 €" bzw. null → „Preis auf Anfrage").
- **`App\Http\Controllers\ShopController`** — `index` (Grid, Markenfilter
  `?marke=<brand_id>`, Pagination 12) und `show` (Detail; unveröffentlicht/
  verkauft = 404, bewusst kein 403 — Interna bleiben unsichtbar). Läuft im
  Tenant-Kontext (Routen-Middleware), alle Queries auf der Tenant-DB.
- **`routes/tenant.php`** — `shop.index` (`/`) und `shop.show`
  (`/uhren/{watch}`), Middleware `web` + `InitializeTenancyByDomain` +
  `PreventAccessFromCentralDomains`.
- **Views `resources/views/shop/`** — `layout` (Header mit Händlername,
  Footer mit Kontakt-Anker + dezentem Händler-Login), `index` (Hero,
  Marken-Pills, Produktgrid, Empty-State, eigene Pagination), `show`
  (Foto-Galerie mit Thumbnail-Wechsel per Vanilla-JS, Preis/Chips,
  Anfrage-Box mit Referenz, gruppierte Spezifikationstabelle, „Das könnte
  Sie auch interessieren"), `partials/watch-card` (Kachel: Bild → Marke →
  Modell → Specs → Preis).
- **Filament** — WatchForm-Tab „Shop & Beschreibung" mit Sektion
  „Öffentlicher Shop" (Veröffentlichen-Toggle + Verkaufspreis);
  Shop-Spalte (Icon) in der Bestandsliste.

## Design-Entscheidungen

- **Pagination selbst gerendert**: Laravels Vendor-Pagination-Views liegen
  außerhalb des Tailwind-`@source`-Scans (`resources/**`) — ihre Klassen
  fehlen im Production-Build. Schlichtes Vor/Zurück + Seitenzähler reicht.
- **Markenfilter zeigt nur vertretene Marken** (über die Brand-IDs der
  veröffentlichten Uhren) — kein Filter-Eintrag führt ins Leere.
- **Fotos** über `Watch::photoUrls()` (Media Library, tenant-isolierte
  Auslieferung via `/tenancy/assets/...`); Empty-State mit Uhren-Icon
  statt gebrochener Bilder.
- **Verbindlicher Sofortkauf** (`PurchaseWatchAction`): Uhren MIT
  Festpreis haben den Button „Jetzt verbindlich kaufen" → Kaufseite
  `/uhren/{watch}/kaufen` (Adressformular + Checkbox, Button-Lösung
  „Jetzt zahlungspflichtig kaufen"). Beim Kauf: Guards unter DB-Sperre
  auf der Uhr (Doppelkauf-Race), Käufer-Kontakt per E-Mail
  wiedererkannt/angelegt (mit Adresse), Uhr → **RESERVIERT** (raus aus
  dem Shop, noch KEIN Verkaufsbeleg). Mails: `OrderConfirmationMail`
  an den Käufer (verbindlicher Kauf, Lieferadresse, Zahlungsblock +
  GiroCode, Verwendungszweck „Kauf {Referenz} {Nachname}") und
  `OrderReceivedMail` an die Inhaber (Reply-To Käufer, Panel-Link,
  Hinweis: nach Zahlungseingang über „Verkaufen" abschließen).
- **Anfrage-Formular statt Checkout**: Der Shop ist ein Schaufenster,
  kein Warenkorb-System. Die Detailseite hat ein echtes Anfrage-Formular
  (Name/E-Mail/Telefon/Nachricht, WatchInquiryRequest) — die Anfrage geht
  als `WatchInquiryMail` an die INHABER des Betriebs (Fallback: Admins,
  zuletzt Absenderadresse), mit Reply-To des Kunden und Direktlink ins
  Panel. Bewusst KEIN automatischer Kontakt im Kundenstamm (erst eine
  echte Geschäftsbeziehung macht Interessenten zu Kontakten).
  POST `/uhren/{watch}/anfrage` mit throttle:5,1.

## Tests (`tests/Feature/ShopTest.php`)

- Listing zeigt nur veröffentlichte UND verkäufliche Uhren
- Preisformat vs. „Preis auf Anfrage"
- Markenfilter
- Detailseite 200 (inkl. Preis mit Nachkommastellen, Spezifikationen)
- 404 für unveröffentlichte und verkaufte Uhren

WICHTIG (etabliertes Muster): Nach Test-HTTP-Requests auf die Tenant-Domain
`tenancy()->end()` im `finally` — sonst räumt PHPUnit auf der bereits
gelöschten Tenant-Verbindung auf und maskiert das Testergebnis.

## Mögliche Erweiterungen

- Anfrage-Formular mit Lead-Erfassung (POST → Contact + Notification)
- Betriebsdaten des Händlers (Kontakt-E-Mail, Telefon, Impressum) als
  Tenant-Einstellungen → Footer/Anfrage-Box
- Sortierung, Preisfilter, Merkliste, SEO (Sitemap, strukturierte Daten)
