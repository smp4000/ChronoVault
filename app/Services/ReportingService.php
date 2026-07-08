<?php

/**
 * =========================================================================
 * ReportingService — Kennzahlen-Aggregation für Dashboards (Modul 9)
 * =========================================================================
 *
 * Zweck:
 *   Liefert die Auswertungen für die Dashboard-Widgets: Umsatz/Marge je
 *   Monat, Verkaufs-Kennzahlen (inkl. Ø Standzeit), Bestand nach Status
 *   und Top-Marken nach gebundenem Kapital.
 *
 * WARUM Aggregation in PHP statt SQL-Datumsfunktionen:
 *   Monats-Gruppierung wäre DB-spezifisch (DATE_FORMAT vs. to_char) —
 *   Migrationen und Queries müssen DB-agnostisch bleiben (ADR-001,
 *   lokal MariaDB/sqlite, Produktion PostgreSQL). Die Datenmengen pro
 *   Tenant (Belege eines Jahres) sind klein genug für PHP.
 *
 * Margen-Semantik:
 *   Marge = Verkaufspreis − Einkaufspreis der Uhr; Verkäufe OHNE
 *   hinterlegten Einkaufspreis fließen in den Umsatz, aber NICHT in die
 *   Marge ein (sonst würden sie als 100 % Marge zählen).
 *
 * Aufrufer: App\Filament\App\Widgets (Sales*, InventoryByStatus, TopBrands)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\WatchStatus;
use App\Models\Transaction;
use App\Models\Watch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportingService
{
    /**
     * Deutsche Kurz-Monatsnamen — bewusst ohne Locale-Abhängigkeit
     * (setlocale/intl ist auf Hosting-Umgebungen unzuverlässig).
     *
     * @var array<int, string>
     */
    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez',
    ];

    /**
     * Umsatz, Marge und Verkaufsanzahl je Monat — lückenlos über die
     * letzten $months Monate (Monate ohne Verkauf erscheinen mit 0).
     *
     * @return array<int, array{month: string, label: string, revenue: float, margin: float, count: int}>
     */
    public function salesByMonth(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        // Leere Monats-Buckets vorbereiten (lückenlose Achse fürs Chart)
        $buckets = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $buckets[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'label' => self::MONTH_LABELS[(int) $month->format('n')].' '.$month->format('y'),
                'revenue' => 0.0,
                'margin' => 0.0,
                'count' => 0,
            ];
        }

        foreach ($this->salesSince($start) as $sale) {
            // getAttribute + instanceof: Date-Casts sind für die statische
            // Analyse als string typisiert (etabliertes Larastan-Muster).
            $transactedAt = $sale->getAttribute('transacted_at');

            if (! $transactedAt instanceof Carbon) {
                continue;
            }

            $key = $transactedAt->format('Y-m');

            if (! array_key_exists($key, $buckets)) {
                continue;
            }

            $price = (float) $sale->price;
            $buckets[$key]['revenue'] += $price;
            $buckets[$key]['count']++;

            $purchasePrice = $sale->watch?->purchase_price;

            if ($purchasePrice !== null) {
                $buckets[$key]['margin'] += $price - (float) $purchasePrice;
            }
        }

        return array_values($buckets);
    }

    /**
     * Verkaufs-Kennzahlen über die letzten $months Monate:
     * Umsatz, Marge, Anzahl und Ø Standzeit (Einkauf → Verkauf, nur
     * Verkäufe mit hinterlegtem Einkaufsdatum).
     *
     * @return array{revenue: float, margin: float, count: int, average_days_in_stock: float|null}
     */
    public function salesTotals(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $sales = $this->salesSince($start);

        $revenue = 0.0;
        $margin = 0.0;
        $stockDays = [];

        foreach ($sales as $sale) {
            $revenue += (float) $sale->price;

            $watch = $sale->watch;

            if ($watch?->purchase_price !== null) {
                $margin += (float) $sale->price - (float) $watch->purchase_price;
            }

            $purchaseDate = $watch?->getAttribute('purchase_date');
            $transactedAt = $sale->getAttribute('transacted_at');

            if ($purchaseDate instanceof Carbon && $transactedAt instanceof Carbon) {
                $stockDays[] = $purchaseDate->diffInDays($transactedAt, absolute: true);
            }
        }

        return [
            'revenue' => $revenue,
            'margin' => $margin,
            'count' => $sales->count(),
            'average_days_in_stock' => $stockDays === []
                ? null
                : round(array_sum($stockDays) / count($stockDays), 1),
        ];
    }

    /**
     * Bestand nach Status (nur belegte Status, Reihenfolge wie im Enum) —
     * Basis für das Status-Doughnut.
     *
     * @return array<string, int> Deutsches Status-Label => Anzahl
     */
    public function inventoryByStatus(): array
    {
        $counts = Watch::query()
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];

        foreach (WatchStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            if ($count > 0) {
                $result[$status->getLabel()] = $count;
            }
        }

        return $result;
    }

    /**
     * Top-Marken des UNVERKAUFTEN Bestands nach gebundenem Kapital
     * (Einkaufswert); Uhren ohne Einkaufspreis zählen nur in die Stückzahl.
     *
     * @return array<int, array{brand: string, count: int, value: float}>
     */
    public function topBrandsByInventoryValue(int $limit = 5): array
    {
        $groups = Watch::query()
            ->whereNot('status', WatchStatus::Sold->value)
            ->with('brand')
            ->get()
            ->groupBy('brand_id');

        return $groups
            ->map(fn (Collection $watches): array => [
                'brand' => $watches->first()->brand->name,
                'count' => $watches->count(),
                'value' => (float) $watches->sum(fn (Watch $watch): float => (float) $watch->purchase_price),
            ])
            ->sortByDesc('value')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Verkaufs-Belege seit $start inkl. Uhr (für Marge/Standzeit).
     *
     * @return Collection<int, Transaction>
     */
    private function salesSince(Carbon $start): Collection
    {
        return Transaction::query()
            ->where('type', TransactionType::Sale->value)
            ->whereDate('transacted_at', '>=', $start)
            ->with('watch')
            ->get();
    }
}
