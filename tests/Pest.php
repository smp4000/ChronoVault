<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * =========================================================================
 * Pest.php — Zentrale Pest-Testkonfiguration
 * =========================================================================
 *
 * Zweck:
 *   Bindet die Laravel-TestCase-Basisklasse an alle Feature-Tests und
 *   stellt projektweite Test-Helper bereit.
 *
 * Verantwortlichkeiten:
 *   - Feature-Tests erhalten die Laravel-Anwendung (Tests\TestCase)
 *   - Unit-Tests bleiben bewusst framework-frei (schnell, isoliert)
 *
 * Nutzung:
 *   php artisan test        (führt alle Pest-Tests aus)
 *   php artisan test --filter=<Name>
 *
 * Mögliche Erweiterungen:
 *   - RefreshDatabase global für Feature-Tests aktivieren, sobald
 *     Domänenmodule mit DB-Zugriff getestet werden (Modul 1+)
 *   - Eigene Expectations (expect()->extend(...)) für Domänenobjekte
 * =========================================================================
 */
pest()->extend(TestCase::class)
    // Zentrale Test-DB (sqlite :memory:) vor jedem Test frisch migrieren.
    ->use(RefreshDatabase::class)
    ->in('Feature');
