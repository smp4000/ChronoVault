<?php

/**
 * =========================================================================
 * RecordValuationAction — Marktwert-Bewertung erfassen
 * =========================================================================
 *
 * Zweck:
 *   Legt den Bewertungs-Datensatz an (Historie) und spiegelt den Wert
 *   in die Schnellzugriffsfelder der Uhr (current_market_value,
 *   last_valuation_at) — aber nur, wenn die Bewertung nicht ÄLTER ist
 *   als die aktuellste vorhandene (nachgetragene Historie überschreibt
 *   den aktuellen Wert nicht).
 *
 * Wunschlisten-Alarm:
 *   Bei Uhren mit Status "Wunschliste" prüft JEDE neue Bewertung den
 *   Zielpreis: Marktwert auf/unter Ziel → einmalige Alarm-Mail
 *   (wishlist_notified_at = Spam-Schutz); Preis über Ziel → Alarm
 *   wieder scharfstellen. Gilt damit für die nächtliche Wertermittlung
 *   UND die manuelle Bewertung im Panel.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Valuations;

use App\Enums\ValuationSource;
use App\Enums\WatchStatus;
use App\Mail\WishlistPriceAlertMail;
use App\Models\Valuation;
use App\Models\Watch;
use App\Support\TenantNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RecordValuationAction
{
    /**
     * @param  array{source: string|ValuationSource, market_value: float|string, value_low?: float|string|null, value_high?: float|string|null, valued_at?: string|\DateTimeInterface|null, summary?: string|null, source_urls?: array<int, string>|null, notes?: string|null}  $data
     */
    public function execute(Watch $watch, array $data): Valuation
    {
        $valuedAt = Carbon::parse($data['valued_at'] ?? now());

        $valuation = $watch->valuations()->create([
            'source' => $data['source'],
            'market_value' => $data['market_value'],
            'value_low' => $data['value_low'] ?? null,
            'value_high' => $data['value_high'] ?? null,
            'valued_at' => $valuedAt,
            'summary' => $data['summary'] ?? null,
            'source_urls' => $data['source_urls'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Schnellzugriff nur aktualisieren, wenn dies die neueste Bewertung ist.
        $lastValuationAt = $watch->getAttribute('last_valuation_at');

        if ($lastValuationAt === null || ! $valuedAt->lt(Carbon::parse($lastValuationAt)->startOfDay())) {
            $watch->forceFill([
                'current_market_value' => $data['market_value'],
                'last_valuation_at' => $valuedAt,
            ])->saveQuietly();

            $this->handleWishlistAlert($watch->refresh(), $data['summary'] ?? null);
        }

        return $valuation;
    }

    /**
     * Zielpreis-Alarm für Wunschlisten-Uhren (einmalig je Unterschreitung).
     */
    private function handleWishlistAlert(Watch $watch, ?string $summary): void
    {
        if ($watch->getAttribute('status') !== WatchStatus::Wishlist
            || $watch->wishlist_target_price === null) {
            return;
        }

        // Preis über Ziel → Alarm wieder scharfstellen
        if (! $watch->wishlistTargetReached()) {
            if ($watch->getAttribute('wishlist_notified_at') !== null) {
                $watch->forceFill(['wishlist_notified_at' => null])->saveQuietly();
            }

            return;
        }

        // Bereits alarmiert → kein Mail-Spam
        if ($watch->getAttribute('wishlist_notified_at') !== null) {
            return;
        }

        try {
            Mail::to(TenantNotifications::recipients())
                ->send(new WishlistPriceAlertMail($watch, $summary));
        } catch (Throwable $exception) {
            report($exception);
        }

        $watch->forceFill(['wishlist_notified_at' => now()])->saveQuietly();
    }
}
