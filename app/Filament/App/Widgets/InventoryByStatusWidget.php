<?php

/**
 * =========================================================================
 * InventoryByStatusWidget — Bestandszusammensetzung nach Status (Modul 9)
 * =========================================================================
 *
 * Zweck:
 *   Doughnut über alle Uhren nach Bestandsstatus (An Lager, Kommission,
 *   In Auktion, …) — Farben folgen der Status-Semantik.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Services\ReportingService;
use Filament\Widgets\ChartWidget;

class InventoryByStatusWidget extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Bestand nach Status';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('watches.view') ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $byStatus = app(ReportingService::class)->inventoryByStatus();

        return [
            'labels' => array_keys($byStatus),
            'datasets' => [
                [
                    'data' => array_values($byStatus),
                    // Blau-geführte Palette (Design-Leitplanke), semantische
                    // Abstufung: Lager kräftig, Verkauft neutral grau.
                    'backgroundColor' => ['#1e40af', '#f59e0b', '#0ea5e9', '#6366f1', '#a855f7', '#9ca3af'],
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    /**
     * Legende unter dem Chart — Standard-Optionen von Chart.js sind
     * fürs Doughnut zu eng.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
