<?php

/**
 * =========================================================================
 * TenantStatsWidget — Mandanten-Kennzahlen auf dem zentralen Dashboard
 * =========================================================================
 *
 * Zweck:
 *   Zeigt dem Plattform-Team auf einen Blick den Zustand des Geschäfts:
 *   Gesamtzahl, aktive Mandanten, Testphasen und Sperrungen.
 *
 * Performance-Hinweis:
 *   Eine einzige Aggregat-Query statt vier einzelner COUNTs — bei
 *   wachsender Mandantenzahl relevant.
 *
 * Mögliche Erweiterungen:
 *   - Trend-Charts (Neuanmeldungen pro Monat)
 *   - MRR-Kennzahlen, sobald das Abrechnungs-Modul existiert
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\Central\Widgets;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Eine Query, gruppiert nach Status — statt vier COUNT-Queries.
        $countsByStatus = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            Stat::make('Mandanten gesamt', (string) $countsByStatus->sum())
                ->description('Alle aktiven Betriebe')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Aktiv', (string) ($countsByStatus[TenantStatus::Active->value] ?? 0))
                ->description('Zahlende Mandanten')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Testphase', (string) ($countsByStatus[TenantStatus::Trial->value] ?? 0))
                ->description('Potenzielle Neukunden')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('info'),

            Stat::make('Gesperrt', (string) ($countsByStatus[TenantStatus::Suspended->value] ?? 0))
                ->description('Zugriff verweigert')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger'),
        ];
    }
}
