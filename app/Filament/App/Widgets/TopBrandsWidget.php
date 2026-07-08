<?php

/**
 * =========================================================================
 * TopBrandsWidget — Top-Marken nach gebundenem Kapital (Modul 9)
 * =========================================================================
 *
 * Zweck:
 *   Balkendiagramm der 5 Marken mit dem höchsten Einkaufswert im
 *   unverkauften Bestand — zeigt, wo das Kapital des Betriebs steckt.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Services\ReportingService;
use Filament\Widgets\ChartWidget;

class TopBrandsWidget extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Top-Marken im Bestand';

    protected ?string $description = 'Einkaufswert unverkaufter Uhren';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('watches.view') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $brands = app(ReportingService::class)->topBrandsByInventoryValue(5);

        return [
            'labels' => array_map(
                fn (array $brand): string => $brand['brand'].' ('.$brand['count'].')',
                $brands
            ),
            'datasets' => [
                [
                    'label' => 'Einkaufswert (€)',
                    'data' => array_map(fn (array $brand): float => round($brand['value'], 2), $brands),
                    'backgroundColor' => 'rgba(30, 64, 175, 0.75)',
                    'borderRadius' => 6,
                ],
            ],
        ];
    }

    /**
     * Horizontale Balken lesen sich bei Markennamen besser.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
