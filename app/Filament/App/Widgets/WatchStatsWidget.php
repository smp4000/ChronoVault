<?php

/**
 * =========================================================================
 * WatchStatsWidget — Bestandskennzahlen auf dem Tenant-Dashboard
 * =========================================================================
 *
 * Zweck:
 *   Zeigt dem Betrieb auf einen Blick den Zustand seines Bestands:
 *   verkaufsbereite Uhren, Reservierungen, Service-Fälle und Verkäufe.
 *
 * Sichtbarkeit:
 *   Nur für Benutzer mit watches.view (canView) — das Dashboard rendert
 *   das Widget für andere Rollen gar nicht erst.
 *
 * Performance-Hinweis:
 *   Eine einzige Aggregat-Query statt vier einzelner COUNTs
 *   (gleiches Muster wie TenantStatsWidget, zentral).
 *
 * Mögliche Erweiterungen:
 *   - Bestandswert-Kennzahlen, sobald Preise existieren (Modul 5)
 *   - Verkaufs-Trend-Chart (Modul 9 Reporting)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Enums\WatchStatus;
use App\Models\Watch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WatchStatsWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('watches.view') ?? false;
    }

    protected function getStats(): array
    {
        // Eine Query, gruppiert nach Status — statt vier COUNT-Queries.
        $countsByStatus = Watch::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sellable = collect(WatchStatus::sellableStatuses())
            ->sum(fn (WatchStatus $status): int => (int) ($countsByStatus[$status->value] ?? 0));

        return [
            Stat::make('Verkaufsbereit', (string) $sellable)
                ->description('An Lager & Kommission')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Reserviert', (string) ($countsByStatus[WatchStatus::Reserved->value] ?? 0))
                ->description('Zusagen offen')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Im Service', (string) ($countsByStatus[WatchStatus::InService->value] ?? 0))
                ->description('Revision / Reparatur')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),

            Stat::make('Verkauft', (string) ($countsByStatus[WatchStatus::Sold->value] ?? 0))
                ->description('Gesamt (Historie)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),
        ];
    }
}
