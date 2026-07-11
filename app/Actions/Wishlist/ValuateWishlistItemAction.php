<?php

/**
 * =========================================================================
 * ValuateWishlistItemAction — Wunschmodell bewerten + Zielpreis-Alarm
 * =========================================================================
 *
 * Zweck:
 *   Recherchiert den aktuellen Marktwert eines Wunschmodells (KI über
 *   den bestehenden MarketValueLookupService — als Recherche-Basis
 *   dient eine transiente Watch mit Marke/Modell/Referenz) und pflegt
 *   die Beobachtungsfelder. Liegt der Marktwert AUF/UNTER dem
 *   Zielpreis, geht einmalig die WishlistPriceAlertMail an den
 *   Händler; steigt der Preis wieder über das Ziel, wird der Alarm
 *   automatisch wieder scharfgestellt (notified_at = null).
 *
 * Aufrufer: wishlist:update-values (nächtlich) und die
 * „Jetzt bewerten"-Aktion in der Wunschlisten-Tabelle.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Wishlist;

use App\Enums\UserRole;
use App\Mail\WishlistPriceAlertMail;
use App\Models\User;
use App\Models\Watch;
use App\Models\WishlistItem;
use App\Services\MarketValueLookupService;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ValuateWishlistItemAction
{
    public function __construct(
        private readonly MarketValueLookupService $lookup,
    ) {}

    public function execute(WishlistItem $item): WishlistItem
    {
        // Transiente Uhr als Recherche-Basis — der Lookup-Service
        // arbeitet mit fullName()/Zustand, beides ist hier abgedeckt.
        $watch = new Watch([
            'model_name' => $item->model_name,
            'reference_number' => $item->reference_number,
        ]);
        $watch->setRelation('brand', $item->brand);

        $data = $this->lookup->lookup($watch);

        $item->fill([
            'current_market_value' => $data->marketValue,
            'value_low' => $data->valueLow,
            'value_high' => $data->valueHigh,
            'last_valuation_at' => now(),
        ]);

        // Re-Arm: Preis über Ziel → Alarm wieder scharfstellen
        if (! $item->isTargetReached()) {
            $item->notified_at = null;
        }

        $item->save();

        // Zielpreis erreicht + Alarm scharf → einmalige Mail
        if ($item->isTargetReached() && $item->getAttribute('notified_at') === null) {
            try {
                Mail::to($this->recipients())
                    ->send(new WishlistPriceAlertMail($item->refresh(), $data->summary));
            } catch (Throwable $exception) {
                report($exception);
            }

            $item->forceFill(['notified_at' => now()])->save();
        }

        return $item->refresh();
    }

    /**
     * Empfänger wie bei Shop-Benachrichtigungen: konfigurierte Adresse,
     * sonst Inhaber, dann Administratoren, zuletzt mail.from.
     *
     * @return array<int, string>
     */
    private function recipients(): array
    {
        $configured = tenant('notification_email');

        if (is_string($configured) && $configured !== '') {
            return [$configured];
        }

        $owners = User::role(UserRole::Owner->value)->pluck('email')->all();

        if ($owners !== []) {
            return $owners;
        }

        $admins = User::role(UserRole::Admin->value)->pluck('email')->all();

        return $admins !== [] ? $admins : [(string) config('mail.from.address')];
    }
}
