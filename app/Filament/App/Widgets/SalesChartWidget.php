<?php

/**
 * =========================================================================
 * SalesChartWidget — Umsatz & Marge je Monat (Modul 9)
 * =========================================================================
 *
 * Zweck:
 *   Liniendiagramm der letzten 12 Monate: Umsatz und Marge aus dem
 *   ReportingService (lückenlose Monatsachse, Monate ohne Verkauf = 0).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Services\ReportingService;
use Filament\Widgets\ChartWidget;

class SalesChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Umsatz & Marge';

    protected ?string $description = 'Verkäufe der letzten 12 Monate';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->can('transactions.view') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = app(ReportingService::class)->salesByMonth(12);

        return [
            'labels' => array_column($months, 'label'),
            'datasets' => [
                [
                    'label' => 'Umsatz (€)',
                    'data' => array_map(fn (array $month): float => round($month['revenue'], 2), $months),
                    'borderColor' => '#1e40af',
                    'backgroundColor' => 'rgba(30, 64, 175, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Marge (€)',
                    'data' => array_map(fn (array $month): float => round($month['margin'], 2), $months),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
        ];
    }
}
