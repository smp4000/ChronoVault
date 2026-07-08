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

**Automatischer Start** (`Auction::startIfDue()`): Geplante Auktionen
mit erreichter Startzeit werden auf „Läuft" gesetzt — dreifach
abgesichert: (1) Katalog-/Losseiten-Aufruf und Gebotsabgabe starten
fällige Auktionen sofort (Fallback ohne Cron), (2) Scheduler
`tenants:run auctions:start-due` jede Minute (routes/console.php;
Produktion: Cron `schedule:run`, lokal `php artisan schedule:work`).

**Automatischer Abschluss** (`Auction::completeIfFullySettled()`):
Sobald das LETZTE offene Los abgerechnet ist (Zuschlag, Rückgang oder
Rückzug), setzt die SettleLotAction die laufende Auktion auf
„Abgeschlossen". Danach sind keine Einlieferungen mehr möglich
(acceptsLots) — für Nachzügler den Status manuell zurück auf „Läuft"
stellen.

**Automatische Abwicklung bei Auktionsende** (`FinalizeAuctionAction`):
Erreicht eine Online-/Hybrid-Auktion ihr Ende, wird jedes offene Los
automatisch abgerechnet — Zuschlag an den Höchstbietenden NUR wenn das
Limit (reserve_price) erreicht ist bzw. keines gesetzt wurde, sonst
Rückgang (Status-Restore). Trigger: Scheduler
`tenants:run auctions:finalize-due` (jede Minute) + Katalog-Fallback
beim Seitenaufruf (try/catch, bricht die Seite nie). Idempotent.

**Gewinner-Mail** (`AuctionWonMail`, grüne Glückwunsch-Kopfzeile):
- Schritt 1: signierter Link (14 Tage, `shop.auctions.winner`) zur
  Erfassung der Liefer-/Rechnungsdaten — aktualisiert den beim
  Zuschlag angelegten Käufer-Kontakt (WinnerDetailsRequest,
  signed-Middleware auch fürs POST auf die volle signierte URL).
- Schritt 2: Zahlungsblock mit Kontoinhaber/IBAN/BIC (Betriebsdaten-
  Seite im App-Panel, settings.manage; gespeichert im zentralen
  Tenant-data-JSON), Betrag, Verwendungszweck und **GiroCode-QR**
  (EPC069-12, `App\Support\GiroCode`, endroid/qr-code + GD, als
  cid-Anhang eingebettet — data-URIs blockieren Mail-Clients).
  Ohne hinterlegte IBAN entfällt der Block (Hinweis auf separate
  Zahlungsinfos).
- CLI-Versand setzt den URL-Root auf die Tenant-Domain
  (FinalizeDueAuctionsCommand::forceTenantUrlRoot).

**Mindestgebot** (`AuctionLot::minimumNextBid()`): Höchstgebot +
Mindest-Erhöhungsschritt, sonst Startpreis (Fallback: untere Schätzung,
50 €). Der Schritt ist PRO AUKTION einstellbar
(`auctions.bid_increment`, Standard 100 €, Feld im Auktionsformular) —
der Gebotsbetrag selbst ist frei wählbar (auch krumme Beträge),
bewusst KEINE Staffel.

**Los-Code** (`auction_lots.lot_code`, unique): Öffentliche Kennung als
6 GROSSBUCHSTABEN (z. B. „KXQWBA"), automatisch beim Anlegen erzeugt
(kollisionsgeprüft inkl. Papierkorb). Erscheint überall in der
Außendarstellung (Katalog, Mails, Verwendungszweck, Beleg-Notiz,
Panel-Modals); die numerische lot_number bleibt für die
Katalog-Reihenfolge.

**Limit-Kommunikation**: Liegt das Höchstgebot unter dem Limit,
ersetzt die `ReserveNotMetMail` (Bernstein-Kopfzeile) die normale
Bestätigung — Gebot erfasst, aber ohne Limit-Erreichen kein Zuschlag;
Aufforderung zum Erhöhen. Das Limit selbst wird NIE genannt
(Geschäftsgeheimnis des Einlieferers); die Los-Seite zeigt öffentlich
nur den Badge „Limit noch nicht erreicht" (`AuctionLot::isReserveMet()`).

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
- **Live-Countdown** (`shop/partials/countdown.blade.php`): tickende
  Restzeit („Endet in 2 Tage 04:12:33") auf Katalog-, Auktions- und
  Los-Seite, solange das Bietfenster offen ist; bei 0 lädt die Seite
  einmalig neu (Server rendert das geschlossene Bietfenster).
  Vanilla-JS, @once gegen doppelte Skripte.
- Panel: Höchstgebot-Spalte (+Anzahl) im LotsRelationManager,
  „Gebote"-Modal (`resources/views/filament/auction-lot-bids.blade.php`),
  Zuschlag-Modal: Käufer-Feld gruppiert „Bieter dieses Loses" +
  „Kundenstamm" (Höchstbietender vorausgewählt; Bieter-Auswahl setzt
  den Hammerpreis live). Zuschlag an einen Bieter legt den Kontakt
  automatisch an (SettleLotAction::contactFromBid — Wiedererkennung
  per E-Mail, kein Duplikat bei Stammkunden; Typ Privatperson).

### Bieter-E-Mails

Jedes angenommene Gebot löst eine `BidConfirmationMail` an den Bieter
aus (Premium-Design: weiße Karte, blaue Kopfzeile mit Gebotsbetrag,
Uhr-Kachel mit Foto, Eckdaten, **Verbindlichkeits-Hinweis**, CTA zum
Los; Tabellen-Layout + Inline-CSS für E-Mail-Clients —
`resources/views/emails/bid-confirmation.blade.php`).

Zusätzlich erhält der ABGELÖSTE Höchstbietende eine `OutbidMail`
(„Sie wurden überboten" — dunkle Kopfzeile mit neuem Höchstgebot,
altes Gebot durchgestrichen, Mindestgebot, CTA „Jetzt nachbieten";
`resources/views/emails/outbid.blade.php`). Bewusst NUR der abgelöste
Höchstbietende: Alle früheren Bieter wurden bereits informiert, als
sie selbst überboten wurden — kein Mail-Spam. Erhöht jemand sein
eigenes Gebot (gleiche E-Mail, case-insensitiv), gibt es keine
Überboten-Mail. Der bisherige Höchstbietende wird innerhalb der
DB-Transaktion unter der Sperre ermittelt (race-sicher).
Versand synchron NACH der DB-Transaktion; ein Mail-Fehler verhindert
das Gebot nie (try/catch + report). In Produktion mit Horizon auf
ShouldQueue umstellen. CLI-Versand (Tinker/Jobs) braucht
`URL::forceRootUrl(...)`, sonst zeigen Links auf die zentrale Domain.

### Tests (`tests/Feature/OnlineAuctionTest.php`, 6 Tests)

Guards (Saalauktion/Bietfenster/Endzeit), Mindestgebot + Staffel,
Bestätigungsmail (Empfänger + gerendeter Inhalt), Zuschlag an Bieter
(Kontakt-Anlage/-Wiedererkennung), öffentliche Seiten (Entwurf 404),
HTTP-Bietflow (Erfolg + Ablehnung als Formularfehler, IP gespeichert).

## Mögliche Erweiterungen

- Weitere Bieter-Mails: überboten / Zuschlag erhalten / Auktion endet bald
- Live-Aktualisierung des Gebotsstands (Polling/Websockets)
- Bieter-Konten mit Verifizierung; Maximalgebote (Bietagent)
- Aufgeld (buyer's premium) + Einlieferer-Provision → Abrechnung
- Einlieferer (consignor_contact_id) am Los für Kommissionsabrechnung
