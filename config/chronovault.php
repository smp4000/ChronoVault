<?php

/**
 * =========================================================================
 * config/chronovault.php — Plattformweite ChronoVault-Einstellungen
 * =========================================================================
 *
 * Zweck:
 *   Zentrale, applikationsspezifische Konfiguration, die nicht in ein
 *   Paket-Config-File gehört. Hier leben Plattform-Konstanten wie das
 *   Domain-Suffix für Mandanten-Subdomains.
 *
 * Nutzung:
 *   config('chronovault.tenant_domain_suffix')
 *
 * Mögliche Erweiterungen:
 *   - Pläne/Limits (max. Uhren pro Plan, max. Benutzer)
 *   - Feature-Flags pro Umgebung
 * =========================================================================
 */

return [

    /*
     * Suffix für automatisch erzeugte Mandanten-Domains.
     * Lokal: "localhost" → demo.localhost (Browser lösen *.localhost auf 127.0.0.1 auf).
     * Produktion: z. B. "chronovault.app" → demo.chronovault.app.
     */
    'tenant_domain_suffix' => env('TENANT_DOMAIN_SUFFIX', 'localhost'),

];
