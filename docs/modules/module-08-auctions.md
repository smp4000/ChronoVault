# Modul 8: Auktionen (+ 8b: Online-Bieten)

> Auktions-Ereignisse mit Losverwaltung — für Auktionshäuser und Händler,
> die eigene Versteigerungen durchführen oder Uhren einliefern.
> Modul 8b ergänzt den öffentlichen Auktionskatalog mit Online-Geboten
> auf der Tenant-Domain (siehe unten).

## Zweck

Eine **Auktion** (Saal/Online/Hybrid) bündelt **Lose** (`auction_lots`):
Jedes Los verknüpft eine Uhr mit Losnummer, Schätzpreis-Spanne, Limit
(reserve_price) und dem späteren Ergebnis. Der Uhren-Status wird über den
gesamten Lebenszyklus synchron gehalten:

```
Einliefern (AddLotToAuctionAction)   Uhr → „In Auktion" (Status gemerkt)
├── Zuschlag  (SettleLotAction::sold)      → Verkaufsbeleg (Modul 5), Uhr „Verkauft"
├── Rückgang  (SettleLotAction::unsold)    → Status-RESTORE (Kommission bleibt Kommission)
└── Rückzug   (SettleLotAction::withdraw)  → Status-RESTORE
```

## Datenbank (`2026_07_09_000000_create_auctions_tables.php`)

**auctions**: UUID, title, description, venue (AuctionVenue), location,
status (AuctionStatus, default draft), starts_at/ends_at, currency, notes,
created_by_user_id, SoftDeletes.

**auction_lots**: UUID, auction_id (cascadeOnDelete — Policy schützt),
watch_id (restrictOnDelete), buyer_contact_id (restrictOnDelete),
lot_number (unique je Auktion), status (AuctionLotStatus, default open),
previous_watch_status (Restore-Ziel), starting_price, estimate_low/high,
reserve_price, hammer_price, currency, settled_at, notes, SoftDeletes.

## Enums

- **AuctionStatus**: draft/scheduled/live/completed/cancelled;
  `acceptingLots()` = Draft/Scheduled/Live (nur dann darf eingeliefert werden)
- **AuctionVenue**: saleroom/online/hybrid
- **AuctionLotStatus**: open/sold/unsold/withdrawn
- **WatchStatus**: NEU `InAuction` („In Auktion") — bewusst NICHT in
  `sellableStatuses()`: Uhren in Auktionen verschwinden aus dem Shop
  und gelten in Kennzahlen nicht als frei verkäuflich.

## Actions

- **AddLotToAuctionAction** — Guards (deutsche RuntimeException für die UI):
  Auktion nimmt Lose an, Uhr nicht verkauft, Uhr nicht bereits als offenes
  Los (auktionsübergreifend!). Losnummer fortlaufend (max+1, inkl.
  soft-gelöschter — keine Nummern-Wiederverwendung) oder explizit.
  Merkt previous_watch_status, Uhr → InAuction.
- **SettleLotAction** — `sold()` (Hammerpreis Pflicht; Verkaufsbeleg über
  RecordSaleAction mit Käufer/Zahlungsart, Notiz „Auktionszuschlag — Los N";
  Uhr → Verkauft), `unsold()` / `withdraw()` (Status-Restore — nur wenn die
  Uhr noch InAuction ist, gleiche Semantik wie CompleteServiceAction).
  Bereits abgerechnete Lose sind gesperrt (assertOpen).

## Policies & Berechtigungen

- `auctions.*` im TenantDatabaseSeeder (view alle, create/update +Employee,
  delete nur Owner/Admin) — per `tenants:seed` auf Bestandsmandanten verteilt.
- **AuctionPolicy**: Löschen nur ohne offene Lose (inkl. soft-gelöschter) —
  sonst blieben Uhren dauerhaft „In Auktion".
- **AuctionLotPolicy**: teilt `auctions.*`; zugeschlagene Lose sind
  Beleg-Historie und nicht löschbar.
- **ContactPolicy** erweitert: Kontakte mit Auktionskäufen (buyer) sind
  nicht löschbar.

## Filament (`App\Filament\App\Resources\Auctions`)

- **AuctionResource** (Gruppe „Verkauf", Icon Megaphone): Formular mit
  Titel/Form/Status/Ort/Zeitraum; Tabelle mit Los-Kennzahlen
  (withCount/withSum: Lose, Zuschläge, Erlös — keine N+1).
- **LotsRelationManager**: „Uhr einliefern" (CreateAction->using →
  AddLotToAuctionAction; Action-Guards erscheinen als Danger-Notification,
  Halt bricht sauber ab), Zuschlag-Modal (Hammerpreis, Käufer, Zahlungsart),
  Rückgang/Rückzug mit Bestätigung. Bearbeiten/Löschen nur für offene Lose.

## Tests (`tests/Feature/AuctionTest.php`, 6 Tests)

Einliefern + fortlaufende Nummern + Status-Sync; alle drei Guards;
Zuschlag inkl. Verkaufsbeleg-Prüfung (Achtung: der WatchObserver legt für
purchase_price bereits einen Ankauf-Beleg an — nach Typ filtern!);
Restore bei Rückgang/Rückzug + kein Restore bei zwischenzeitlicher
Änderung; Rollen-Berechtigungen; Lösch-Schutz (Auktion/Los/Käufer).

---

## Modul 8b: Online-Bieten (öffentlicher Auktionskatalog)

### Konzept

Bieter brauchen KEIN Konto (v1): leichtgewichtige Identität per
Name + E-Mail (+ optional Telefon, IP wird intern protokolliert).
Das Auktionshaus prüft die Gebote im Panel und erteilt den Zuschlag
manuell über die bestehende SettleLotAction — das höchste Online-Gebot
ist dabei im Zuschlag-Modal vorbefüllt.

**Bietfenster** (`Auction::isBiddingOpen()`): Austragungsform Online
oder Hybrid + Status „Läuft" + Endzeit (falls gesetzt) nicht
überschritten. Saalauktionen zeigen den Katalog nur zur Ansicht.

**Mindestgebot** (`AuctionLot::minimumNextBid()`): Höchstgebot +
Erhöhungsschritt, sonst Startpreis (Fallback: untere Schätzung, 50 €).
Erhöhungsstaffel (`bidIncrementFor`): <100→10, <500→25, <1000→50,
<2000→100, <5000→200, <10000→500, <50000→1000, sonst 2500.

**Race-Schutz**: Die PlaceBidAction prüft das Mindestgebot in einer
DB-Transaktion mit `lockForUpdate` auf den Gebot-Zeilen des Loses —
zwei gleichzeitige Gebote können nicht beide „gerade so" passieren.

**Datenschutz**: Der öffentliche Katalog zeigt nur Höchstgebot und
Anzahl — nie Bieternamen. Die Gebotsliste (mit Kontaktdaten) gibt es
nur im Panel („Gebote"-Modal, auctions.view).

### Bausteine

- `auction_bids` (uuid, auction_lot_id cascade, bidder_name/email/phone,
  amount, currency, ip_address) + Model `AuctionBid`
- `App\Actions\Auctions\PlaceBidAction` — alle Guards + Race-Schutz
- `App\Http\Requests\PlaceBidRequest` — Formalvalidierung, deutsche Meldungen
- `App\Http\Controllers\AuctionCatalogController` — index/show/lot/bid;
  sichtbar sind Geplant/Läuft/Abgeschlossen (Entwurf/Abgesagt → 404)
- Routen (`routes/tenant.php`): `/auktionen`, `/auktionen/{id}`,
  `/auktionen/{id}/los/{id}` + POST `/bieten` (throttle:10,1)
- Views `resources/views/shop/auctions/` (Shop-Design in Blau):
  index (Live-Badge mit Puls), show (Loskacheln mit Schätzpreis/
  Höchstgebot/Zuschlag), lot (Galerie, Gebotsstand, Formular mit
  Mindestgebot-Anzeige, Kurzdaten) — „Auktionen"-Link im Shop-Header
- Panel: Höchstgebot-Spalte (+Anzahl) im LotsRelationManager,
  „Gebote"-Modal (`resources/views/filament/auction-lot-bids.blade.php`),
  Zuschlag-Modal mit Höchstgebot vorbefüllt

### Tests (`tests/Feature/OnlineAuctionTest.php`, 4 Tests)

Guards (Saalauktion/Bietfenster/Endzeit), Mindestgebot + Staffel,
öffentliche Seiten (Entwurf 404), HTTP-Bietflow (Erfolg + Ablehnung
als Formularfehler, IP gespeichert).

## Mögliche Erweiterungen

- E-Mail-Benachrichtigungen (überboten / Zuschlag erhalten)
- Live-Aktualisierung des Gebotsstands (Polling/Websockets)
- Bieter-Konten mit Verifizierung; Maximalgebote (Bietagent)
- Aufgeld (buyer's premium) + Einlieferer-Provision → Abrechnung
- Einlieferer (consignor_contact_id) am Los für Kommissionsabrechnung
