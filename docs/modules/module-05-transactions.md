# Modul 5 — Kauf/Verkauf & Preishistorie

> Stand: 2026-07-08 · Status: ✅ Fertig (Belege/PDF & Inserat-Erstellung folgen später)

## Überblick

Neue Navigation-Gruppe **„Verkauf"** mit Kundenstamm und Kauf-/Verkaufs-
belegen. Die Transaktionen bilden die **Preishistorie** jeder Uhr —
eine Uhr kann mehrfach den Besitzer wechseln (Ankauf → Verkauf →
Rückkauf → …).

## Datenbank-Design (Tenant-DB)

| Tabelle | Kern |
|---|---|
| `contacts` | UUID, type (ContactType), company_name/first_name/last_name (Firma ODER Nachname Pflicht, Form-Validierung), email/phone/Adresse, SoftDeletes |
| `transactions` | UUID, watch_id FK + contact_id FK (beide **restrictOnDelete** — Belege verlieren nie ihre Bezüge), created_by FK, type (purchase/sale), price decimal(12,2), **currency ISO-4217 (Default EUR)**, transacted_at, payment_method, document_number, SoftDeletes; Indizes (watch_id, type) und transacted_at |

Warum currency ab Tag 1: Belege sind Ewigkeitsdaten — eine Währung
nachträglich raten zu müssen wäre fatal.

## Enums

- `TransactionType` (purchase/sale → Ankauf/Verkauf)
- `PaymentMethod` (cash/bank_transfer/card/paypal/financing/trade_in/other)
- `ContactType` (private/dealer/auction_house/other)

## Domain-Actions (Erstellung IMMER hierüber!)

| Action | Wirkung |
|---|---|
| `RecordSaleAction` | Verkaufs-Beleg + Uhr → Status „Verkauft"; `margin()` liefert die Marge ggü. Einkaufspreis (UI-Notification) |
| `RecordPurchaseAction` | Ankauf-Beleg + Sync der purchase_*-Schnellzugriffsfelder; **Rückkauf** holt eine verkaufte Uhr zurück „An Lager" |

`WatchObserver (created)`: Uhren, die direkt mit Einkaufsdaten angelegt
werden, bekommen automatisch ihren Ankauf-Beleg — die Historie ist von
Anfang an vollständig (syncWatch=false, Felder stehen schon).

## Filament (Gruppe „Verkauf")

- **TransactionResource** („An- & Verkäufe"): Liste mit Typ-Badge,
  Kontakt, Preis (EUR), Filtern; Erstellung läuft über
  `CreateTransaction::handleRecordCreation` → Domain-Actions.
  Uhr und Art sind nach Erstellung nicht änderbar (Status-Sync!);
  Storno = Beleg in den Papierkorb + neu erfassen.
- **ContactResource**: Kundenstamm mit Referenz-Schutz (Kontakt mit
  Belegen nicht löschbar — ContactPolicy + FK restrict).
- **TransactionsRelationManager** an der Uhr (Form/Table wiederverwendet,
  withWatch: false; CreateAction->using() → Actions).
- **„Verkaufen"-Schnellaktion** in der Bestandsliste: Modal (Käufer,
  Preis, Datum, Zahlungsart, Belegnr.) → RecordSaleAction → Notification
  mit Marge/Verlust. Sichtbar nur für unverkaufte Uhren + transactions.create.

## Berechtigungen

contacts.* und transactions.* — Semantik wie gehabt: view alle Rollen,
create/update auch Mitarbeiter, delete (Storno) nur Owner/Admin.
Bestandsmandanten: `tenants:migrate` + `tenants:seed` (erledigt).

## Bewusst NICHT in Modul 5

- Beleg-PDFs/Rechnungen (später mit Nummernkreisen + Layout)
- Inserat-Erstellung & öffentlicher Shop (eigenes Modul;
  Design: docs/DESIGN.md — grimmeissen.de in Blau)
- Kennzahlen/Umsatzauswertungen (Modul 9 Reporting)

## Stolperfalle (dokumentiert)

- `*/` in deutschen Docblocks („contacts.*/transactions.*") beendet den
  Kommentar → Parse Error. Formulierungen ohne `*/`-Sequenz wählen.
