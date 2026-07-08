# ChronoVault — Projektstatus

> **Diese Datei ist die Single Source of Truth für den Projektstatus.**
> Sie wird nach JEDEM abgeschlossenen Arbeitsschritt aktualisiert und dient als
> Statusblock-Quelle am Anfang jeder Entwicklungs-Session.
>
> Letzte Aktualisierung: 2026-07-09 (Modul 8b — Online-Bieten)

---

## Aktueller Stand

**Modul 8b (Online-Bieten) abgeschlossen**
([Doku](modules/module-08-auctions.md)). Öffentlicher Auktionskatalog
auf der Tenant-Domain (`/auktionen`, „Auktionen" im Shop-Header):
Auktionsliste (Läuft/Demnächst/Beendet), Loskacheln mit Schätzpreis/
Höchstgebot/Zuschlag, Los-Detailseite mit Galerie und Gebotsformular
(Name + E-Mail, kein Konto — v1). PlaceBidAction erzwingt Bietfenster
(Online/Hybrid + „Läuft" + Endzeit), Mindestgebot (Höchstgebot +
Erhöhungsstaffel bzw. Startpreis) und Race-Schutz (lockForUpdate).
Bieterdaten nie öffentlich; Panel zeigt Höchstgebot-Spalte +
Gebote-Modal, Zuschlag-Modal mit Höchstgebot vorbefüllt. POST mit
throttle:10,1. Live verifiziert (Demo-Auktion auf welle.localhost).
73 Tests grün, PHPStan sauber.

**Nächster Schritt:** Modul 10 (API/Sanctum) ODER Auktions-Ausbau
(E-Mail-Benachrichtigungen, Live-Gebotsstand) ODER Shop-Ausbau.

---

## Module

| # | Modul | Status |
|---|-------|--------|
| 0 | Foundation (Scaffold, Pakete, Panel, Doku) | ✅ Fertig |
| 1 | Tenancy & Benutzer-/Rollenverwaltung ([Doku](modules/module-01-tenancy.md)) | ✅ Fertig |
| 2 | Stammdaten: Marken (Brands) & Kaliber ([Doku](modules/module-02-master-data.md)) | ✅ Fertig |
| 3 | Kernmodul: Uhren (Watches) ([Doku](modules/module-03-watches.md)) | ✅ Fertig |
| 4 | Medienverwaltung ([Doku](modules/module-04-media.md)) | ✅ Fertig |
| 5 | Kauf/Verkauf & Preishistorie ([Doku](modules/module-05-transactions.md)) | ✅ Fertig |
| 6 | Service-Historie & Wartung ([Doku](modules/module-06-service.md)) | ✅ Fertig |
| 7 | Bewertungen & Marktwert ([Doku](modules/module-07-valuations.md)) | ✅ Fertig |
| — | Öffentlicher Shop / Schaufenster ([Doku](modules/shop.md)) | ✅ Fertig |
| 8 | Auktionen ([Doku](modules/module-08-auctions.md)) | ✅ Fertig |
| 9 | Reporting & Dashboards ([Doku](modules/module-09-reporting.md)) | ✅ Fertig |
| 10 | API (Sanctum) & Integrationen | ⬜ Offen |

## Datenbanktabellen

**Zentral (MariaDB `chronovault`):**
- `tenants` (UUID, name, slug, status, data, SoftDeletes), `domains`
- `users` (Plattform-Betreiber), `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `telescope_*`

**Pro Tenant (`cv_tenant_<uuid>`, Migrationen in `database/migrations/tenant/`):**
- `users`, `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `media` (spatie/laravel-medialibrary, uuidMorphs — Collections photos/documents an Watch)
- `brands` (UUID, name unique, country, founded_year, website, is_active, SoftDeletes)
- `calibers` (UUID, brand_id FK restrictOnDelete, movement_type, Kenndaten, unique brand_id+name, SoftDeletes)
- `watches` (UUID, brand_id FK, caliber_id FK nullable, created_by_user_id FK, model/reference/serial/stock_number, condition, status, ownership_status + owner, Chrono24-Attribute [Aufzug, Geschlecht, Gehäuse/Lünette/Glas, Zifferblatt, Band/Schließe, Wasserdichtigkeit, Bandanstoß], functions JSON, Kauf [price/date/location/delivery_scope], Limited Edition, Lagerort, description + notes, Versicherung, photo_slots JSON [Modul 4], photos JSON [KI-Foto-Download], Bewertung [watchcharts_uuid/market_value — Modul 7], Shop [is_published + asking_price], research_data JSON [KI-Lookup], SoftDeletes)
- `contacts` (UUID, type, Firma/Vor-/Nachname, E-Mail/Telefon/Adresse, SoftDeletes)
- `transactions` (UUID, watch_id + contact_id FK restrictOnDelete, created_by FK, type purchase/sale, price, currency, transacted_at, payment_method, document_number, SoftDeletes)
- `service_records` (UUID, watch_id + contact_id FK restrictOnDelete, type, status, previous_watch_status [Restore!], cost/currency, submitted/completed/warranty, SoftDeletes)
- `valuations` (UUID, watch_id FK restrictOnDelete, source, market_value + Spanne, currency, valued_at, summary, source_urls JSON, SoftDeletes)
- `auctions` (UUID, title, venue, location, status, starts_at/ends_at, currency, SoftDeletes)
- `auction_lots` (UUID, auction_id FK cascade, watch_id + buyer_contact_id FK restrictOnDelete, lot_number [unique je Auktion], status, previous_watch_status [Restore!], starting/estimate/reserve/hammer-Preise, settled_at, SoftDeletes)
- `auction_bids` (UUID, auction_lot_id FK cascade, bidder_name/email/phone, amount, currency, ip_address — Online-Gebote ohne Konto, Modul 8b)

## Models

- `App\Models\User` — zentral UND tenant (Connection-Switch); FilamentUser + HasRoles; checkPermissionTo() mit Tenant-Kontext-Guard (zentral entscheiden allein die Policies)
- `App\Models\Tenant` — stancl BaseTenant + SoftDeletes, Custom Columns (name, slug, status)
- `App\Models\Brand` — Tenant; HasUuids + SoftDeletes; hasMany Calibers/Watches (Werkhersteller wie ETA sind auch Brands)
- `App\Models\Caliber` — Tenant; HasUuids + SoftDeletes; belongsTo Brand; hasMany Watches; MovementType-Cast
- `App\Models\Watch` — Tenant; HasUuids + SoftDeletes + Scout Searchable; belongsTo Brand/Caliber; fullName(); Shop: scopePublishedInShop() + formattedAskingPrice()
- `App\Models\Auction` — Tenant; HasUuids + SoftDeletes; hasMany Lots (Katalog-Reihenfolge); acceptsLots()/isCompleted()/openLotsCount()
- `App\Models\AuctionLot` — Tenant; HasUuids + SoftDeletes; belongsTo Auction/Watch/Buyer(Contact); isOpen()/isSold(); Gebote: bids()/highestBidAmount()/minimumNextBid()/bidIncrementFor()
- `App\Models\AuctionBid` — Tenant; HasUuids; belongsTo AuctionLot; Online-Gebot (Name/E-Mail, kein Konto)

## Filament Resources

**Central-Panel (`/admin`, Namespace `App\Filament\Central`):**
- `Tenants\TenantResource` (+ TenantForm, TenantsTable, List/Create/Edit-Pages)

**App-Panel (`/app` auf Tenant-Domains, Namespace `App\Filament\App`):**
- `Users\UserResource` (+ UserForm, UsersTable, List/Create/Edit-Pages)
- `Brands\BrandResource` (Gruppe „Stammdaten"; + BrandForm, BrandsTable, Pages, CalibersRelationManager, Papierkorb/Restore)
- `Calibers\CaliberResource` (Gruppe „Stammdaten"; + CaliberForm, CalibersTable, Pages — Form/Table werden vom RelationManager wiederverwendet, `withBrand: false`)
- `Watches\WatchResource` (Gruppe „Bestand"; + WatchForm als Tab-Layout mit KI-Referenz-Lookup [Referenznummer zuerst, ✨-Action] und abhängigem Kaliber-Select, WatchesTable mit Full-Set-Filter + „Verkaufen"-Schnellaktion, TransactionsRelationManager, Pages, Papierkorb/Restore)
- `Transactions\TransactionResource` (Gruppe „Verkauf"; Erstellung via Domain-Actions in CreateTransaction; Form/Table wiederverwendet vom RelationManager)
- `Contacts\ContactResource` (Gruppe „Verkauf"; Kundenstamm mit Adress-Sektion)
- `ServiceRecords\ServiceRecordResource` (Gruppe „Bestand"; Anlage via StartServiceAction, „Abschließen"-Aktion, ServiceRecordsRelationManager an der Uhr, „In Service"-Schnellaktion in der Bestandsliste)
- `Auctions\AuctionResource` (Gruppe „Verkauf"; Los-Kennzahlen via withCount/withSum; LotsRelationManager mit „Uhr einliefern"/Zuschlag/Rückgang/Rückzug über die Domain-Actions)

**Widgets:**
- `Central\Widgets\TenantStatsWidget` (Mandanten-Kennzahlen, Dashboard)
- `App\Widgets\WatchStatsWidget` (Bestandskennzahlen, Tenant-Dashboard; canView nur mit watches.view)
- `App\Widgets\InventoryValueWidget` (Einkaufs-/Marktwert des Bestands + Wertentwicklung %, Modul 7)
- `App\Widgets\SalesStatsWidget` (Umsatz/Marge/Ø Standzeit 12 Monate, Modul 9; transactions.view)
- `App\Widgets\SalesChartWidget` (Linie: Umsatz + Marge je Monat, volle Breite, Modul 9)
- `App\Widgets\InventoryByStatusWidget` (Doughnut: Bestand nach Status, Modul 9)
- `App\Widgets\TopBrandsWidget` (Balken: Top 5 Marken nach Einkaufswert unverkauft, Modul 9)

## Öffentlicher Shop (außerhalb Filament)

- `App\Http\Controllers\ShopController` — Listing (Markenfilter, Pagination) + Detailseite (404 für Unveröffentlichtes)
- `App\Http\Controllers\AuctionCatalogController` — Auktionskatalog + Online-Gebote (Modul 8b; Entwurf/Abgesagt → 404, Bieterdaten nie öffentlich)
- `App\Http\Requests\PlaceBidRequest` — Formalvalidierung des Gebotsformulars (deutsche Meldungen)
- `routes/tenant.php` — `shop.index` (`/`), `shop.show` (`/uhren/{watch}`), `shop.auctions.*` (`/auktionen...`, Gebots-POST mit throttle:10,1)
- `resources/views/shop/` — layout, index, show, partials/watch-card, auctions/{index,show,lot} (grimmeissen-Stil in Blau, Tailwind only)

## Services

- `App\Services\WatchReferenceLookupService` — KI-Recherche zu Referenznummern: Perplexity sonar-pro (bevorzugt, Web-Suche eingebaut, citations→source_urls) mit Anthropic claude-opus-4-8 als Fallback; JSON-Parsing + Stammdaten-Matching; DTO `WatchReferenceData`; Konfiguration PERPLEXITY_API_KEY / ANTHROPIC_API_KEY
- `App\Services\MarketValueLookupService` — KI-Marktwert-Recherche (Perplexity; Zustand/Lieferumfang/Baujahr im Prompt); DTO `MarketValueData` (Wert, Spanne, Quellen)
- `App\Services\ReportingService` — Dashboard-Kennzahlen (Modul 9): salesByMonth/salesTotals/inventoryByStatus/topBrandsByInventoryValue; DB-agnostische PHP-Aggregation, Margen-Semantik (nur Verkäufe mit Einkaufspreis)

## Actions

- `App\Actions\Tenancy\CreateTenantAction` — komplettes Provisioning
- `App\Actions\Tenancy\DeleteTenantAction` — archive() (Soft) / execute() (endgültig + DB-Löschung)
- `App\Actions\Watches\DownloadWatchPhotosAction` — lädt KI-Bildquellen als Uhrenfotos (public-Disk, tenant-isoliert; max 4; nur image/*)
- `App\Actions\Transactions\RecordSaleAction` — Verkaufs-Beleg + Status „Verkauft" + margin()
- `App\Actions\Transactions\RecordPurchaseAction` — Ankauf-Beleg + purchase_*-Sync; Rückkauf → zurück in Bestand
- `App\Actions\Services\StartServiceAction` — Vorgang anlegen, Status merken, Uhr → „Im Service"
- `App\Actions\Services\CompleteServiceAction` — Abschluss + Status-RESTORE (kein Override bei zwischenzeitlicher Änderung)
- `App\Actions\Valuations\RecordValuationAction` — Bewertungs-Historie + Schnellzugriff-Sync (ältere Nachträge überschreiben nicht)
- `App\Actions\Auctions\AddLotToAuctionAction` — Einliefern mit Guards; Losnummern fortlaufend; Uhr → „In Auktion" (Status gemerkt)
- `App\Actions\Auctions\SettleLotAction` — sold() (Verkaufsbeleg + Uhr „Verkauft"), unsold()/withdraw() (Status-RESTORE)
- `App\Actions\Auctions\PlaceBidAction` — Online-Gebot mit Guards (Bietfenster, Mindestgebot) + Race-Schutz (lockForUpdate)

## Enums

- `App\Enums\TenantStatus` (trial/active/suspended/archived, deutsche Labels, Filament-Contracts)
- `App\Enums\UserRole` (owner/admin/employee/viewer, deutsche Labels, managementRoles())
- `App\Enums\MovementType` (manual/automatic/quartz/solar/spring_drive/smartwatch, deutsche Labels, Filament-Contracts)
- `App\Enums\WatchCondition` (new/unworn/very_good/good/fair, deutsche Labels, Filament-Contracts)
- `App\Enums\WatchStatus` (in_stock/reserved/in_service/consignment/sold, deutsche Labels, sellableStatuses())
- Chrono24-Katalog: `CaseMaterial` (19), `WatchColor` (20), `BraceletMaterial` (18), `GlassType`, `ClaspType`, `DialNumerals`, `WatchGender` — standardisierte Inserat-Attribute statt Freitext
- `App\Enums\OwnershipStatus` (owned/commission/customer_property — Kommissionsgeschäft)
- `App\Enums\WatchFunction` (15 Komplikationen, Mehrfachauswahl als JSON-Array)
- `App\Enums\PhotoSlot` (6 Slots des geführten Foto-Uploads)
- Modul 5: `TransactionType` (purchase/sale), `PaymentMethod` (7 Zahlungsarten), `ContactType` (5 Kontaktarten inkl. Workshop)
- Modul 6: `ServiceType` (8 Service-Arten), `ServiceStatus` (open/in_progress/completed)
- Modul 7: `ValuationSource` (manual/ai_research/external)
- Modul 8: `AuctionStatus` (draft/scheduled/live/completed/cancelled, acceptingLots()), `AuctionVenue` (saleroom/online/hybrid), `AuctionLotStatus` (open/sold/unsold/withdrawn); `WatchStatus` um `in_auction` erweitert (NICHT sellable)

## Jobs

- _Eigene: keine._ Genutzt werden stancl-Jobs: CreateDatabase, MigrateDatabase, SeedDatabase, DeleteDatabase

## Events

- _Eigene: keine._ stancl-Events via TenancyServiceProvider (TenantCreated-Pipeline; TenantDeleted bewusst OHNE DB-Löschung)

## Policies

- `App\Policies\TenantPolicy` — nur zentraler Kontext; forceDelete nur für archivierte
- `App\Policies\UserPolicy` — permission-basiert (users.*), Selbstlöschungs- & Owner-Hierarchie-Schutz
- `App\Policies\BrandPolicy` — master_data.*; Referenz-Schutz (Kaliber & Uhren, inkl. soft-gelöschter)
- `App\Policies\CaliberPolicy` — master_data.*; Referenz-Schutz (Uhren, inkl. soft-gelöschter)
- `App\Policies\WatchPolicy` — permission-basiert (watches.*)
- `App\Policies\ContactPolicy` — contacts.*; Referenz-Schutz (Kontakt mit Belegen, Servicevorgängen ODER Auktionskäufen nicht löschbar)
- `App\Policies\TransactionPolicy` — transactions.*; Löschen (Storno) nur Verwaltung
- `App\Policies\ServiceRecordPolicy` — services.*
- `App\Policies\ValuationPolicy` — valuations.*
- `App\Policies\AuctionPolicy` — auctions.*; Löschen nur ohne offene Lose (inkl. soft-gelöschter)
- `App\Policies\AuctionLotPolicy` — auctions.*; zugeschlagene Lose (Beleg-Historie) nicht löschbar

## Observers

- `App\Observers\TenantObserver` — Slug-Generierung + Kollisionsauflösung (creating)
- `App\Observers\WatchObserver` — Foto-Download nach dem Speichern (saved; nur wenn KI-Bildquellen vorhanden und noch keine Fotos)

## Seeder / Factories

- `Database\Seeders\TenantDatabaseSeeder` — Rollen + Berechtigungen (users.*, roles.manage, settings.manage, master_data.*, watches.*); ruft MasterDataSeeder auf; wird bei jedem Provisioning ausgeführt
- `Database\Seeders\MasterDataSeeder` — Starter-Grundstock (20 Marken, 17 Kaliber), idempotent, respektiert mandantenseitige Löschungen
- `Database\Factories\TenantFactory`, `BrandFactory`, `CaliberFactory`, `WatchFactory`, `AuctionFactory`, `AuctionLotFactory` (+ UserFactory aus dem Skeleton)

## Test-Infrastruktur

- Helper `provisionTenant()` / `destroyTenant()` in `tests/Pest.php` — für alle Feature-Tests nutzbar

## Offene TODOs

- [x] ~~Modul 4~~ → komplett (medialibrary pro Tenant, geführter Foto-Upload, Markenlogos, Upload-Routen tenancy-fähig, watches:migrate-photos)
- [ ] Bild-Conversions/Thumbnails, sobald Queue-Worker läuft (Produktion)
- [ ] Alt-Spalten watches.photos + watches.photo_slots entfernen, sobald alle Tenants migriert sind (Fallback in photoUrls() dann ebenfalls)
- [ ] PERPLEXITY_API_KEY in Produktion setzen (Anthropic optional als Fallback); KI-Lookup ggf. per Queue-Job entkoppeln (aktuell synchron mit set_time_limit 180)
- [ ] Feld-Berechtigung für Einkaufspreis/Versicherungswert (z. B. watches.view_purchase_price — aktuell für alle mit watches.view sichtbar)
- [ ] Modul 7: current_market_value/last_valuation_at/watchcharts_uuid pflegen (Spalten existieren bereits)
- [ ] Berechtigungen neuer Module immer im TenantDatabaseSeeder ergänzen + `tenants:seed` für Bestandsmandanten
- [ ] RoleResource im App-Panel (eigene Rollen pro Mandant; Berechtigung `roles.manage` existiert)
- [ ] Suspended-Tenant-UX: Login wird verweigert (canAccessPanel), aber ohne erklärende Fehlerseite
- [ ] Willkommens-E-Mail für neue Mandanten-Owner (statt Initialpasswort-Übergabe)
- [ ] Redis in Produktion: CacheTenancyBootstrapper aktivieren, permission-Cache zurück auf persistent (ADR-008), Horizon (ADR-002)
- [ ] Meilisearch lokal installieren, Scout-Driver umstellen (ADR-003)
- [ ] Laravel Pulse konfigurieren; Telescope in Produktion deaktivieren
- [ ] Deutsches Sprachpaket (`laravel-lang`) für Framework-Validierungsmeldungen
- [ ] Shop: Anfrage-Formular (Lead → Contact + Notification) statt reiner Anfrage-Box
- [ ] Auktionen: E-Mail-Benachrichtigungen für Bieter (überboten/Zuschlag); Live-Gebotsstand (Polling/Websockets); Demo-Auktion auf „welle" nach dem Testen zurückziehen (Rückzug stellt Uhren-Status wieder her)
- [ ] Shop: Betriebsdaten des Händlers (Kontakt-E-Mail/Telefon/Impressum) als Tenant-Einstellungen für Footer & Anfrage
- [ ] Eigenes Filament-Theme-CSS (`->viteTheme()`) für Premium-Feinschliff

## Mögliche zukünftige Verbesserungen

- Self-Service-Registrierung + Onboarding-Wizard für neue Mandanten
- User Impersonation (stancl Feature) für Support
- Kunden-Portal / öffentlicher Marktplatz, KI-Preisbewertung, Mobile-API
- Audit-Exporte (Versicherung), QR-Etiketten fürs Lager, Webhooks
- Backup vor endgültiger Tenant-Löschung; Lösch-Karenzzeit
