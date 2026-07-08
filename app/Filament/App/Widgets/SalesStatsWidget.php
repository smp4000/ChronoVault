<?php

/**
 * =========================================================================
 * SalesStatsWidget — Verkaufs-Kennzahlen auf dem Tenant-Dashboard (Modul 9)
 * =========================================================================
 *
 * Zweck:
 *   Umsatz, Marge, Verkaufsanzahl und Ø Standzeit der letzten 12 Monate —
 *   aggregiert über den ReportingService (Margen-Semantik siehe dort).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Services\ReportingService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('transactions.view') ?? false;
    }

    protected function getStats(): array
    {
        $totals = app(ReportingService::class)->salesTotals(12);

        return [
            Stat::make('Umsatz (12 Monate)', number_format($totals['revenue'], 0, ',', '.').' €')
                ->description($totals['count'].' Verkäufe')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Marge (12 Monate)', number_format($totals['margin'], 0, ',', '.').' €')
                ->description('Nur Verkäufe mit Einkaufspreis')
                ->descriptionIcon($totals['margin'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($totals['margin'] >= 0 ? 'success' : 'danger'),

            Stat::make(
                'Ø Standzeit',
                $totals['average_days_in_stock'] !== null
                    ? number_format($totals['average_days_in_stock'], 0, ',', '.').' Tage'
                    : '—'
            )
                ->description('Einkauf bis Verkauf')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
