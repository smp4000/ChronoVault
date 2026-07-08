<?php

/**
 * =========================================================================
 * PurchaseWatchAction — Verbindlicher Sofortkauf im Shop (Festpreis)
 * =========================================================================
 *
 * Zweck:
 *   Wickelt den „Jetzt zahlungspflichtig kaufen"-Klick ab:
 *   1. Guards: Uhr noch im Shop verfügbar UND Festpreis hinterlegt —
 *      unter DB-Sperre auf der Uhr (Doppelkauf-Race zweier Käufer).
 *   2. Käufer-Kontakt anlegen bzw. per E-Mail wiedererkennen und mit
 *      den Liefer-/Rechnungsdaten aktualisieren.
 *   3. Uhr → RESERVIERT (nicht Verkauft!): Sie verschwindet sofort aus
 *      dem Shop; den Verkaufsbeleg erstellt der Händler nach
 *      Zahlungseingang über die bestehende Verkaufen-Action.
 *   4. Mails: Kaufbestätigung mit Zahlungsinfos + GiroCode an den
 *      Käufer, Bestell-Benachrichtigung an die Inhaber.
 *
 * Aufrufer: ShopController::purchase (POST /uhren/{watch}/kaufen).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Enums\ContactType;
use App\Enums\UserRole;
use App\Enums\WatchStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderReceivedMail;
use App\Models\Contact;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class PurchaseWatchAction
{
    /**
     * @param  array{first_name?: string|null, last_name: string, email: string, phone?: string|null, street: string, postal_code: string, city: string, country: string}  $data
     */
    public function execute(Watch $watch, array $data): Contact
    {
        $buyer = DB::transaction(function () use ($watch, $data): Contact {
            // Sperre gegen zwei gleichzeitige Käufer derselben Uhr
            $lockedWatch = Watch::query()
                ->lockForUpdate()
                ->findOrFail($watch->getKey());

            $sellable = in_array(
                $lockedWatch->getAttribute('status'),
                WatchStatus::sellableStatuses(),
                true,
            );

            if (! $lockedWatch->is_published || ! $sellable) {
                throw new RuntimeException('Diese Uhr ist leider nicht mehr verfügbar.');
            }

            if ($lockedWatch->asking_price === null) {
                throw new RuntimeException('Diese Uhr hat keinen Festpreis — bitte stellen Sie eine Anfrage.');
            }

            $buyer = $this->buyerContact($data);

            // Reserviert = raus aus dem Shop (nicht sellable), aber noch
            // kein Verkaufsbeleg — der folgt nach Zahlungseingang.
            $lockedWatch->forceFill(['status' => WatchStatus::Reserved])->saveQuietly();

            return $buyer;
        });

        $watch->refresh();

        // Mails NACH der Transaktion — Fehler nur loggen (Kauf steht).
        try {
            Mail::to($buyer->email)->send(new OrderConfirmationMail($watch, $buyer));
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            Mail::to($this->ownerRecipients())->send(new OrderReceivedMail($watch, $buyer));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $buyer;
    }

    /**
     * Käufer per E-Mail wiedererkennen (kein Duplikat), Daten
     * aktualisieren — sonst als Privatperson neu anlegen.
     *
     * @param  array<string, mixed>  $data
     */
    private function buyerContact(array $data): Contact
    {
        $attributes = [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'street' => $data['street'],
            'postal_code' => $data['postal_code'],
            'city' => $data['city'],
            'country' => $data['country'],
        ];

        $existing = Contact::query()->where('email', $data['email'])->first();

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing;
        }

        return Contact::create([
            ...$attributes,
            'type' => ContactType::PrivatePerson,
            'email' => $data['email'],
            'notes' => 'Automatisch angelegt aus Shop-Sofortkauf.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function ownerRecipients(): array
    {
        $owners = User::role(UserRole::Owner->value)->pluck('email')->all();

        if ($owners !== []) {
            return $owners;
        }

        $admins = User::role(UserRole::Admin->value)->pluck('email')->all();

        return $admins !== [] ? $admins : [(string) config('mail.from.address')];
    }
}
