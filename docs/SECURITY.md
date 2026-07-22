# ChronoVault — Sicherheit & DSGVO

> Ergebnis des Sicherheits- und DSGVO-Audits vom 2026-07-22 (drei parallele
> Prüfungen: öffentliche Endpunkte, DSGVO-Datenflüsse, Konfiguration/Auth)
> und Dokumentation der umgesetzten Härtung. Diese Datei ist die
> Referenz für alle künftigen sicherheitsrelevanten Entscheidungen —
> **jedes neue Feature wird gegen die Prinzipien hier geprüft.**

---

## Prinzipien

1. **Öffentliche POST-Endpunkte:** immer `throttle` + FormRequest-Validierung.
2. **E-Mail-Aktionslinks:** immer `temporarySignedRoute` mit Ablauf; ein GET
   darf NIEMALS eine verbindliche Aktion auslösen (Mail-Scanner/Prefetch!) —
   GET zeigt Bestätigungsseite, POST führt aus.
3. **Personenbezogene Daten:** Datenminimierung, dokumentierte Löschfristen
   (PrunePersonalDataCommand), keine Personendaten an Dritte ohne
   Offenlegung in der Datenschutzerklärung.
4. **Neue Formulare:** Datenschutzhinweis + Link auf die Datenschutzerklärung.
5. **Neue Migrationen mit Personenbezug:** Löschregel im
   PrunePersonalDataCommand ergänzen oder Aufbewahrungspflicht dokumentieren.

## Umgesetzte Härtung (2026-07-22)

| Maßnahme | Ort |
|---|---|
| Security-Header global (X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy; HSTS in Produktion) | `app/Http/Middleware/SecurityHeaders.php` |
| `trustProxies` nicht mehr `*`, sondern env-gesteuert (`TRUSTED_PROXIES`, Default: private Netze) — verhindert IP-Spoofing/Throttle-Bypass | `bootstrap/app.php`, DEPLOYMENT.md |
| Passwort-Policy global: min. 12 Zeichen, Groß/Klein, Ziffern (`Password::defaults`) | `app/Providers/AppServiceProvider.php` |
| Seeder bricht in Produktion ohne `CENTRAL_ADMIN_EMAIL/PASSWORD` hart ab (keine Default-Admin-Falle mehr) | `database/seeders/DatabaseSeeder.php` |
| Gegenangebots-Entscheidung: GET = Bestätigungsseite, POST (signed + throttle) = verbindliche Aktion | `ShopController`, `routes/tenant.php`, `shop/proposal-confirm.blade.php` |
| **DSGVO-Löschkonzept**: Gebots-IPs nach 30 Tagen anonymisiert, Gebote geschlossener Lose nach 180 Tagen gelöscht, abgeschlossene Preisvorschläge nach 90 Tagen endgültig gelöscht (löst die Formular-Zusage ein); täglich 01:00 je Mandant | `app/Console/Commands/PrunePersonalDataCommand.php`, `routes/console.php` |
| KI-Datenminimierung: Kundenname geht NICHT mehr an Anthropic/Perplexity; KI-Dienste inkl. Drittlandtransfer im Datenschutz-Prompt offengelegt | `ProposalReplyService`, `LegalTextService` |
| Zentrale Plattform hat eigene Rechtsseiten `/impressum` + `/datenschutz` (Texte: `resources/legal/*.txt`) + Footer-Links | `MarketplaceController::legal`, `marketplace/legal.blade.php` |
| Formular-Transparenz: IP-Speicherhinweis am Gebotsformular; Datenschutzhinweis an der Marktplatz-Anfrage | `shop/auctions/lot.blade.php`, `marketplace/show.blade.php` |
| `SESSION_SECURE_COOKIE=true` + Admin-Credentials + `TRUSTED_PROXIES` als Pflicht im Deployment dokumentiert | `docs/DEPLOYMENT.md`, `.env.example` |

## Bereits vorher solide (Audit-Positivbefunde)

Kein `{!! !!}` mit User-Eingaben (kein XSS-Pfad) · CSRF ohne Ausnahmen ·
alle öffentlichen POSTs gedrosselt · UUIDs überall (keine Enumeration) ·
signierte Upload-/Aktionslinks mit Ablauf · Telescope strikt lokal ·
keine hartkodierten Secrets · Rechnungs-PDFs on-the-fly hinter Auth ·
Tenant-DB/Storage/Queue-Isolation korrekt · Mails ohne Drittbieter-Daten,
Bilder inline (kein Tracking) · nur technisch notwendige Cookies
(kein Consent-Banner nötig, § 25 Abs. 2 TDDDG).

## Offene Punkte (priorisiert)

1. **HOCH — Dokumente-Collection absichern:** `Watch`-`documents`
   (Kaufbelege, Zertifikate, Servicehefte) liegen auf dem öffentlichen
   Tenant-Disk und sind über die stancl-Asset-Route unauthentifiziert
   abrufbar (Media-IDs fortlaufend!). Lösung: Collection auf privaten Disk
   + authentifizierte Download-Route mit Policy-Check; Bestandsdateien
   in Produktion migrieren. (Fotos bleiben bewusst öffentlich.)
2. **MITTEL — Auktions-Start/-Abwicklung aus GET-Requests entfernen:**
   `AuctionCatalogController` triggert `startIfDue/finalizeDueAuctions`
   beim Seitenaufruf (Fallback ohne Cron). In Produktion läuft der
   Scheduler — dann Fallback entfernen oder auf dispatch(Job) umstellen.
3. **MITTEL — AV-Verträge/DPA schließen und ablegen:** Hetzner, Cloudflare,
   Anthropic, Perplexity, Mail-Anbieter (Strato). Juristisch prüfen lassen —
   die KI-generierten Rechtstexte sind Entwürfe ohne Rechtsgewähr.
4. **MITTEL — Aufbewahrungskonzept Kontakte/Belege:** `contacts` ohne
   steuerrelevante Belege nach Zweckfortfall löschen (§ 147 AO nur für
   Belege); Auskunfts-/Löschprozess für Betroffenenanfragen dokumentieren.
5. **MITTEL — Backups:** automatisierte DB-Dumps (zentral + je Tenant-DB)
   + Offsite-Kopie; spatie/laravel-backup ist installiert, aber nicht
   konfiguriert. Restore-Test dokumentieren.
6. **MITTEL — DB-Grants verengen:** `DROP ON *.*` auf `cv_tenant_%`
   begrenzen bzw. stancl `PermissionControlledMySQLDatabaseManager`.
7. **NIEDRIG — Nonce-basierte CSP** (Livewire/Filament-kompatibel).
8. **NIEDRIG — 2FA für /admin und /app** (Filament-Plugin).
9. **NIEDRIG — Gewinner-/Gegenangebots-Links nach Nutzung invalidieren**
   (Status-Flag) und Gültigkeit von 14 auf 7 Tage verkürzen.
10. **NIEDRIG — Captcha-Konsistenz:** `WatchInquiryRequest` validiert die
    Rechenfrage nicht (Preisvorschlag/Registrierung tun es).
11. **NIEDRIG — `SESSION_ENCRYPT=true` erwägen**, `resources/views/welcome.blade.php`
    (Bunny-Fonts-Ballast) entfernen.
