<?php

/**
 * =========================================================================
 * MasterDataSeeder — Stammdaten-Grundausstattung (Marken & Kaliber)
 * =========================================================================
 *
 * Zweck:
 *   Befüllt jede neue Tenant-Datenbank mit einem kuratierten Grundstock
 *   an Uhrenmarken und bekannten Kalibern, damit Händler/Juweliere sofort
 *   arbeiten können statt erst Stammdaten zu tippen. Wird vom
 *   TenantDatabaseSeeder aufgerufen (Provisioning + tenants:seed).
 *
 * Verantwortlichkeiten:
 *   - Idempotenz: firstOrCreate über den (unique) Markennamen bzw.
 *     (brand_id, name) — der Seeder darf beliebig oft laufen, ohne
 *     mandantenspezifische Änderungen zu überschreiben.
 *
 * WARUM pro Tenant statt zentraler Katalog (ADR-009):
 *   Mandanten sollen Stammdaten frei ergänzen/anpassen dürfen (z. B.
 *   Kleinserien-Marken, Eigenmarken) — ein zentraler Katalog bräuchte
 *   Freigabeprozesse und würde die Datenisolation aufweichen.
 *
 * Mögliche Erweiterungen:
 *   - Erweiterung des Grundstocks; Beschreibungstexte; Logos (Modul 4)
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Brand;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Kuratierter Marken-Grundstock: [Name, Land, Gründungsjahr].
     * ETA & Sellita sind Werkhersteller — bewusst als Brands geführt
     * (siehe Docblock im Brand-Model).
     *
     * @var array<int, array{0: string, 1: string, 2: int}>
     */
    private const BRANDS = [
        ['Rolex', 'Schweiz', 1905],
        ['Omega', 'Schweiz', 1848],
        ['Patek Philippe', 'Schweiz', 1839],
        ['Audemars Piguet', 'Schweiz', 1875],
        ['Vacheron Constantin', 'Schweiz', 1755],
        ['A. Lange & Söhne', 'Deutschland', 1845],
        ['Jaeger-LeCoultre', 'Schweiz', 1833],
        ['IWC Schaffhausen', 'Schweiz', 1868],
        ['Breitling', 'Schweiz', 1884],
        ['TAG Heuer', 'Schweiz', 1860],
        ['Tudor', 'Schweiz', 1926],
        ['Cartier', 'Frankreich', 1847],
        ['Panerai', 'Italien', 1860],
        ['Zenith', 'Schweiz', 1865],
        ['Grand Seiko', 'Japan', 1960],
        ['Seiko', 'Japan', 1881],
        ['Glashütte Original', 'Deutschland', 1845],
        ['NOMOS Glashütte', 'Deutschland', 1990],
        ['ETA', 'Schweiz', 1793],
        ['Sellita', 'Schweiz', 1950],
    ];

    /**
     * Bekannte Kaliber je Marke:
     * Markenname => [Name, Werktyp, Gangreserve h, Frequenz vph, Steine, Ø mm].
     * null = Kenndatum unbekannt/nicht anwendbar (z. B. vph bei Spring Drive).
     *
     * @var array<string, array<int, array{0: string, 1: MovementType, 2: ?int, 3: ?int, 4: ?int, 5: ?string}>>
     */
    private const CALIBERS = [
        'Rolex' => [
            ['3235', MovementType::Automatic, 70, 28800, 31, '28.5'],
            ['3135', MovementType::Automatic, 48, 28800, 31, '28.5'],
            ['4131', MovementType::Automatic, 72, 28800, 47, '30.5'],
        ],
        'Omega' => [
            ['8800', MovementType::Automatic, 55, 25200, 35, '26.0'],
            ['8900', MovementType::Automatic, 60, 25200, 39, '29.0'],
            ['3861', MovementType::Manual, 50, 21600, 26, '27.0'],
        ],
        'Patek Philippe' => [
            ['324 S C', MovementType::Automatic, 45, 28800, 29, '27.0'],
        ],
        'Zenith' => [
            ['El Primero 3600', MovementType::Automatic, 60, 36000, 35, '30.0'],
        ],
        'Grand Seiko' => [
            ['9SA5', MovementType::Automatic, 80, 36000, 47, '31.0'],
            ['9R65', MovementType::SpringDrive, 72, null, 30, '30.0'],
        ],
        'Seiko' => [
            ['NH35', MovementType::Automatic, 41, 21600, 24, '27.4'],
        ],
        'NOMOS Glashütte' => [
            ['Alpha', MovementType::Manual, 43, 21600, 17, '23.3'],
        ],
        'ETA' => [
            ['2824-2', MovementType::Automatic, 38, 28800, 25, '25.6'],
            ['7750', MovementType::Automatic, 44, 28800, 25, '30.0'],
            ['6497-1', MovementType::Manual, 46, 18000, 17, '36.6'],
        ],
        'Sellita' => [
            ['SW200-1', MovementType::Automatic, 38, 28800, 26, '25.6'],
            ['SW300-1', MovementType::Automatic, 42, 28800, 25, '25.6'],
        ],
    ];

    public function run(): void
    {
        foreach (self::BRANDS as [$name, $country, $foundedYear]) {
            $brand = Brand::withTrashed()->firstOrCreate(
                ['name' => $name],
                ['country' => $country, 'founded_year' => $foundedYear],
            );

            // Vom Mandanten gelöschte Grundstock-Marken NICHT wiederbeleben
            // (withTrashed im Lookup verhindert zugleich einen Unique-Konflikt).
            if ($brand->trashed()) {
                continue;
            }

            foreach (self::CALIBERS[$name] ?? [] as [$caliberName, $movementType, $powerReserve, $frequency, $jewels, $diameter]) {
                $brand->calibers()->withTrashed()->firstOrCreate(
                    ['name' => $caliberName],
                    [
                        'movement_type' => $movementType,
                        'power_reserve_hours' => $powerReserve,
                        'frequency_vph' => $frequency,
                        'jewels' => $jewels,
                        'diameter_mm' => $diameter,
                    ],
                );
            }
        }
    }
}
