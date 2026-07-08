# Modul 6 — Service-Historie & Wartung

> Stand: 2026-07-08 · Status: ✅ Fertig

## Überblick

Servicevorgänge pro Uhr (Revision, Reparatur, Politur, Batteriewechsel,
Wasserdichtigkeitsprüfung, Regulierung, Band-Service) mit Werkstatt,
Kosten, Zeitraum und Service-Garantie — in der Gruppe „Bestand"
(„Service & Wartung") und als Reiter an der Uhr.

## Kernmechanik: Status-Sync mit RESTORE

Der Clou gegenüber einem naiven Status-Setzen:

1. **Start** (`StartServiceAction`): merkt sich den aktuellen
   Uhren-Status in `previous_watch_status` und setzt „Im Service".
2. **Abschluss** (`CompleteServiceAction`): stellt den gemerkten Status
   wieder her — **eine Kommissionsuhr kommt als Kommission zurück**,
   nicht pauschal „An Lager" (Fallback nur ohne gemerkten Status).
3. **Kein Restore**, wenn die Uhr zwischenzeitlich anders vergeben wurde
   (z. B. verkauft) — der Abschluss überschreibt nur „Im Service".

## Datenbank (Tenant-DB)

`service_records`: UUID, watch_id + contact_id FK (restrictOnDelete),
created_by FK, type (ServiceType), status (ServiceStatus),
previous_watch_status, description, cost + currency, submitted_at,
completed_at, warranty_until, document_number, notes, SoftDeletes,
Index (watch_id, status).

## Enums

- `ServiceType` (8 Arten inkl. Revision, Reparatur, Wasserdichtigkeitsprüfung)
- `ServiceStatus` (open/in_progress/completed)
- `ContactType` um **Workshop** („Werkstatt/Service") ergänzt —
  Werkstätten sind normale Kontakte; ContactPolicy-Referenz-Schutz
  deckt jetzt auch Servicevorgänge ab.

## Filament

- **ServiceRecordResource** (Gruppe „Bestand", Sort 20): Liste mit
  Status-/Art-Badges, Kosten, Filtern; Anlage via StartServiceAction
  (handleRecordCreation); Status ist KEIN Formularfeld (Workflow!).
- **„Abschließen"-Aktion** (Tabelle + RelationManager): Modal mit
  Abschlussdatum, endgültigen Kosten, Garantie → CompleteServiceAction.
- **ServiceRecordsRelationManager** an der Uhr (Form/Table
  wiederverwendet, withWatch: false).
- **„In Service"-Schnellaktion** in der Bestandsliste (sichtbar wenn
  nicht verkauft/nicht im Service + services.create).

## Berechtigungen

services.* — Semantik wie überall (view alle, create/update auch
Mitarbeiter, delete nur Verwaltung). Bestandsmandanten migriert + geseedet.

## Mögliche Erweiterungen

- Erinnerungen „nächster Service fällig" (z. B. 5 Jahre nach Abschluss)
- Servicekosten in die Margen-Betrachtung einbeziehen (Modul 9)
- Dokumente (Kostenvoranschlag, Servicebeleg) an den Vorgang hängen
  (Media Library — Muster aus Modul 4 vorhanden)
