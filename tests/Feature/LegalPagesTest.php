<?php

/**
 * =========================================================================
 * LegalPagesTest — Rechtsseiten & DSGVO-Grundausstattung (Shop)
 * =========================================================================
 *
 * Abgedeckt:
 *   - /impressum, /datenschutz, /widerruf rendern die Betriebsdaten-Texte
 *   - Ohne Inhalt: deutlicher Hinweis statt leerer Seite
 *   - Footer verlinkt alle drei Rechtsseiten
 * =========================================================================
 */

declare(strict_types=1);

it('serves the legal pages with tenant content and footer links', function () {
    $tenant = provisionTenant();

    $tenant->update([
        'imprint' => "Muster Uhrenhandel GmbH\nUhrmacherweg 1\n10115 Berlin",
        'privacy_policy' => 'Wir verarbeiten Ihre Daten ausschließlich zur Abwicklung Ihrer Anfragen und Käufe.',
        // revocation_policy bewusst leer → Hinweis-Fallback
    ]);

    try {
        $base = 'http://'.$tenant->primaryDomain();

        // Impressum mit Inhalt
        $this->get($base.'/impressum')
            ->assertOk()
            ->assertSee('Impressum')
            ->assertSee('Muster Uhrenhandel GmbH');

        // Datenschutz mit Inhalt
        $this->get($base.'/datenschutz')
            ->assertOk()
            ->assertSee('Datenschutzerklärung')
            ->assertSee('zur Abwicklung Ihrer Anfragen');

        // Widerruf ohne Inhalt → Hinweis für den Betreiber
        $this->get($base.'/widerruf')
            ->assertOk()
            ->assertSee('Widerrufsbelehrung')
            ->assertSee('noch kein Inhalt hinterlegt');

        // Footer-Links auf der Shop-Startseite
        $this->get($base.'/')
            ->assertOk()
            ->assertSee('/impressum')
            ->assertSee('/datenschutz')
            ->assertSee('/widerruf');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});
