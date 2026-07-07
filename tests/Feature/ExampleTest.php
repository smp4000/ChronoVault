<?php

/**
 * Feature-Smoke-Tests: Prüfen, dass die Anwendung grundsätzlich bootet.
 * Dienen als Frühwarnsystem für kaputte Service-Provider oder Konfiguration.
 */
it('returns a successful response on the root route', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

/**
 * Smoke-Test für das Filament-Admin-Panel: Der Login muss erreichbar sein.
 * WARUM: Ein Boot-Fehler in einem PanelProvider würde genau hier auffallen.
 */
it('shows the admin panel login page', function () {
    $response = $this->get('/admin/login');

    $response->assertStatus(200);
});
