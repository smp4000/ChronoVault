# Modul 8: Auktionen

> Auktions-Ereignisse mit Losverwaltung — für Auktionshäuser und Händler,
> die eigene Versteigerungen durchführen oder Uhren einliefern.

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

## Mögliche Erweiterungen

- Aufgeld (buyer's premium) + Einlieferer-Provision → Abrechnung
- Gebot-Historie (bids) für Live-Auktionen
- Auktions-Katalog im öffentlichen Shop (Modul Shop) veröffentlichen
- Einlieferer (consignor_contact_id) am Los für Kommissionsabrechnung
