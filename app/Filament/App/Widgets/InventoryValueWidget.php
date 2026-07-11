<?php

/**
 * =========================================================================
 * InventoryValueWidget — Bestandswert auf dem Tenant-Dashboard (Modul 7)
 * =========================================================================
 *
 * Zweck:
 *   Einkaufswert vs. aktueller Marktwert des UNVERKAUFTEN Bestands
 *   plus Wertentwicklung in Prozent. Marktwerte stammen aus den
 *   Bewertungen (current_market_value-Schnellzugriff).
 *
 * Hinweis Genauigkeit:
 *   Die Entwicklung vergleicht nur Uhren, die BEIDE Werte haben —
 *   sonst würden unbewertete Uhren das Ergebnis verzerren.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Enums\WatchStatus;
use App\Models\Watch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryValueWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('watches.view') ?? false;
    }

    protected function getStats(): array
    {
        // Verkauft raus (kein Bestand mehr), Wunschliste raus (nicht besessen)
        $base = Watch::query()->whereNotIn('status', [
            WatchStatus::Sold->value,
            WatchStatus::Wishlist->value,
        ]);

        $purchaseTotal = (float) (clone $base)->sum('purchase_price');
        $marketTotal = (float) (clone $base)->sum('current_market_value');

        // Entwicklung nur über Uhren mit BEIDEN Werten (faire Basis).
        $comparable = (clone $base)
            ->whereNotNull('purchase_price')
            ->whereNotNull('current_market_value');

        $comparablePurchase = (float) (clone $comparable)->sum('purchase_price');
        $comparableMarket = (float) (clone $comparable)->sum('current_market_value');

        $developmentStat = Stat::make('Wertentwicklung', '—')
            ->description('Bewertungen fehlen noch')
            ->descriptionIcon('heroicon-m-chart-bar')
            ->color('gray');

        if ($comparablePurchase > 0.0) {
            $percent = ($comparableMarket - $comparablePurchase) / $comparablePurchase * 100;
            $developmentStat = Stat::make('Wertentwicklung', number_format($percent, 1, ',', '.').' %')
                ->description('Marktwert vs. Einkauf (nur bewertete Uhren)')
                ->descriptionIcon($percent >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($percent >= 0 ? 'success' : 'danger');
        }

        return [
            Stat::make('Einkaufswert Bestand', number_format($purchaseTotal, 0, ',', '.').' €')
                ->description('Unverkaufte Uhren')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('info'),

            Stat::make('Marktwert Bestand', number_format($marketTotal, 0, ',', '.').' €')
                ->description('Laut aktuellen Bewertungen')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            $developmentStat,
        ];
    }
}
