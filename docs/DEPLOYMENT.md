# ChronoVault auf Hetzner + CloudPanel — Schritt-für-Schritt

> Anleitung für den Livegang, geschrieben für Einsteiger ohne
> Server-Erfahrung. Dauer: 1–2 Stunden. Laufende Kosten: ~4,50 €/Monat
> (Hetzner CX22) + Domain (~1 €/Monat). Cloudflare und CloudPanel sind
> kostenlos.

## Was Sie brauchen

- Eine **Domain** (z. B. bei Netcup, INWX oder direkt bei Cloudflare
  registriert) — im Folgenden `ihre-domain.de`
- Ein **Hetzner-Konto** (https://accounts.hetzner.com — E-Mail,
  Ausweis-/Zahlungsverifizierung, Kreditkarte/PayPal/SEPA)
- Ein **Cloudflare-Konto** (kostenlos, https://dash.cloudflare.com)

**Warum Cloudflare?** ChronoVault braucht Wildcard-Subdomains
(`haendler.ihre-domain.de`). Cloudflare liefert dafür kostenlos
Wildcard-DNS **und** Wildcard-SSL — ohne Cloudflare wäre das
Zertifikats-Handling deutlich fummeliger.

---

## Schritt 1: Server bei Hetzner anlegen (10 Min.)

1. https://console.hetzner.cloud → einloggen → **Neues Projekt**
   („ChronoVault") → **Server hinzufügen**.
2. Einstellungen:
   - **Standort:** Falkenstein oder Nürnberg (Deutschland)
   - **Image:** **Ubuntu 24.04** — WICHTIG: nicht das neueste Ubuntu
     nehmen! CloudPanel unterstützt nur 24.04/22.04; auf neueren
     Images bricht der Installer mit „Database Engine … not supported"
     ab. Falls schon falsch erstellt: Server → „Neuaufbau"/„Rebuild"
     mit Ubuntu 24.04 (löscht den Server-Inhalt), danach lokal
     `ssh-keygen -R <SERVER-IP>` ausführen (der Host-Key hat sich
     geändert — die SSH-Warnung ist dann erwartbar).
   - **Typ:** Shared vCPU x86 → **CX22** (2 vCPU, 4 GB RAM, 40 GB — reicht lange)
   - **Networking:** IPv4 aktiviert lassen (kostet ~0,60 €/Mon., ist nötig)
   - **SSH-Key:** Wenn Sie keinen haben, Feld leer lassen — Hetzner
     schickt das Root-Passwort per E-Mail (beim ersten Login ändern).
   - **Name:** chronovault
3. **Erstellen & kaufen.** Nach ~1 Minute steht in der Übersicht die
   **IP-Adresse** des Servers (z. B. `95.217.x.x`) — notieren.
4. Empfohlen: In der Cloud Console unter **Firewalls** eine Firewall
   anlegen und dem Server zuweisen — eingehend nur TCP **22** (SSH),
   **80**, **443** (Web) und **8443** (CloudPanel) erlauben.

## Schritt 2: DNS bei Cloudflare (10 Min.)

1. Cloudflare → **Add a site** → `ihre-domain.de` → Free-Plan.
2. Cloudflare zeigt zwei **Nameserver** an — diese beim
   Domain-Anbieter als Nameserver der Domain eintragen (dauert bis zu
   ein paar Stunden, meist Minuten).
3. In Cloudflare unter **DNS → Records** zwei Einträge anlegen
   (Proxy-Status: **orange Wolke an**):
   - `A` | Name `@` | Inhalt: Server-IP
   - `A` | Name `*` | Inhalt: Server-IP  ← das ist die Wildcard!
4. Unter **SSL/TLS → Overview**: Modus **Full (strict)**.
5. Unter **SSL/TLS → Origin Server → Create Certificate**: Standard
   (RSA, `*.ihre-domain.de` + `ihre-domain.de`, 15 Jahre) →
   **Zertifikat und Private Key** in zwei Textdateien zwischenspeichern
   (brauchen wir in Schritt 4).

## Schritt 3: CloudPanel installieren (10 Min.)

Per SSH auf den Server (Windows: PowerShell öffnen):

```powershell
ssh root@SERVER-IP
```

Dann auf dem Server:

```bash
apt update && apt -y upgrade
curl -sS https://installer.cloudpanel.io/ce/v2/install.sh -o install.sh
DB_ENGINE=MARIADB_11.4 bash install.sh
```

Nach ~5 Minuten: `https://SERVER-IP:8443` im Browser öffnen
(Zertifikatswarnung einmalig bestätigen) → **Admin-Benutzer anlegen**.
Das ist ab jetzt Ihre Server-Oberfläche.

## Schritt 4: Site anlegen (10 Min.)

1. CloudPanel → **Sites → Add Site → Create a PHP Site**
   - Domain: `ihre-domain.de`
   - Application: Laravel 12 (falls angeboten, sonst „Generic")
   - PHP-Version: **8.3**
   - Site User: `chronovault` + sicheres Passwort (notieren!)
2. Site öffnen → **Domains**: `*.ihre-domain.de` als weitere Domain
   hinzufügen (die Wildcard für die Händler-Shops). Hat die CloudPanel-
   Version keinen Domains-Reiter: im **Vhost**-Editor in BEIDEN Zeilen
   `server_name ihre-domain.de www1.ihre-domain.de;` die Wildcard
   ergänzen: `… *.ihre-domain.de;`
3. **Vhost-Pflichtblock für Uhrenfotos:** nginx behandelt alle Adressen
   mit Bild-/JS-Endung als statische Dateien — die tenant-isolierten
   Fotos (`/tenancy/assets/….jpg`) liefert aber Laravel dynamisch aus.
   Im **Vhost**-Editor im ERSTEN großen server-Block (der mit
   `listen 443`) direkt ÜBER der Zeile, die mit
   `location ~* ^.+\.(css|js|` beginnt, diesen Block einfügen:

   ```nginx
   location ^~ /tenancy/ {
       proxy_pass http://127.0.0.1:8080;
       proxy_set_header Host $http_host;
       proxy_set_header X-Forwarded-Host $http_host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
   }
   ```

   → Speichern. (`^~` hat Vorrang vor den Endungs-Regeln.)
3. **SSL/TLS → Actions → Import Certificate**: das
   Cloudflare-Origin-Zertifikat + Private Key aus Schritt 2 einfügen.
4. **Settings → Root Directory**: auf `chronovault/public` stellen
   (setzen wir in Schritt 6, wenn der Code liegt — Pfad wird
   `htdocs/ihre-domain.de/chronovault/public`).

## Schritt 5: Datenbank + Rechte (5 Min.)

CloudPanel → Site → **Databases → Add Database**: Name `chronovault`,
User `chronovault`. WICHTIG: Die Multi-Tenancy legt pro Händler eine
eigene Datenbank an (`cv_tenant_…`) — dafür braucht der User
Zusatzrechte. Per SSH als root:

```bash
mysql -u root
```

```sql
GRANT ALL PRIVILEGES ON `cv\_tenant\_%`.* TO 'chronovault'@'localhost';
GRANT CREATE, DROP ON *.* TO 'chronovault'@'localhost';
FLUSH PRIVILEGES;
```

## Schritt 6: ChronoVault deployen (20 Min.)

Per SSH als **Site-User** (nicht root!):

```bash
ssh chronovault@SERVER-IP
cd htdocs/ihre-domain.de
git clone https://github.com/smp4000/ChronoVault.git chronovault
cd chronovault
composer install --no-dev --optimize-autoloader
```

> Privates GitHub-Repo? Dann vorher unter GitHub → Settings →
> Developer settings → Personal Access Token (nur `repo`-Recht)
> erstellen und beim Clone als Passwort verwenden.

Node für den Frontend-Build (einmalig als root: `apt install -y nodejs npm`):

```bash
npm install && npm run build
```

`.env` anlegen (`cp .env.example .env`, dann `nano .env`) — die
wichtigen Zeilen:

```ini
APP_NAME=ChronoVault
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ihre-domain.de
APP_TIMEZONE=Europe/Berlin

# Multi-Tenancy
CENTRAL_DOMAIN=ihre-domain.de
TENANT_DOMAIN_SUFFIX=ihre-domain.de

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=chronovault
DB_USERNAME=chronovault
DB_PASSWORD=<Datenbank-Passwort aus CloudPanel>

QUEUE_CONNECTION=database
SESSION_DRIVER=database   # falls lokal so; sonst Wert aus lokaler .env übernehmen

# Mail (wie lokal, Strato)
MAIL_MAILER=smtp
MAIL_HOST=smtp.strato.de
MAIL_PORT=465
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...

PERPLEXITY_API_KEY=pplx-...
```

Dann:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # legt den zentralen Admin an
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Jetzt in CloudPanel den **Root Directory** auf
`chronovault/public` stellen (Schritt 4.4). Test:
`https://ihre-domain.de/admin` → zentrales Login.

## Schritt 7: Cron einrichten (5 Min.)

CloudPanel → Site → **Cron Jobs → Add Cron Job**, zwei Einträge
(Template „Every Minute"):

```
* * * * *  php /home/chronovault/htdocs/ihre-domain.de/chronovault/artisan schedule:run >> /dev/null 2>&1
* * * * *  php /home/chronovault/htdocs/ihre-domain.de/chronovault/artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Damit laufen: Auktions-Start/-Abwicklung (minütlich), nächtliche
Wertermittlung (00:00) und die Queue (Hobby-tauglich ohne Supervisor).

## Schritt 8: Ersten Händler anlegen & testen

1. `https://ihre-domain.de/admin` → Mandanten → neu anlegen
   (Slug `welle` → Domain `welle.ihre-domain.de` entsteht automatisch,
   Wildcard-DNS + Wildcard-Zertifikat greifen sofort).
2. `https://welle.ihre-domain.de` → Shop; `/app` → Händler-Login.
3. Betriebsdaten (Bankverbindung) im Panel hinterlegen, Testgebot
   abgeben → Mail-Empfang prüfen.

## Updates einspielen (jedes Mal)

```bash
ssh chronovault@SERVER-IP
cd htdocs/ihre-domain.de/chronovault
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan tenants:migrate
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Wenn etwas nicht funktioniert

- **Weißer Bildschirm/500:** `storage/logs/laravel.log` ansehen
  (`tail -50 storage/logs/laravel.log`).
- **Subdomain lädt nicht:** DNS-Wildcard (`A *`) in Cloudflare prüfen;
  Site-Alias `*.ihre-domain.de` in CloudPanel prüfen.
- **Mails kommen nicht an:** MAIL_-Werte prüfen; `php artisan tinker`
  → `Mail::raw('Test', fn($m) => $m->to('ihre@mail.de')->subject('Test'));`
- **„Access denied" beim Anlegen eines Mandanten:** DB-Rechte aus
  Schritt 5 prüfen.
