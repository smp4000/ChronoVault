<?php

/**
 * =========================================================================
 * TenantNotifications — Empfänger für Händler-Benachrichtigungen
 * =========================================================================
 *
 * Zweck:
 *   EINE Stelle für die Empfänger-Auflösung interner Mails (Anfragen,
 *   Preisvorschläge, Bestellungen, Wunschlisten-Alarme):
 *   1. Benachrichtigungs-Adresse aus den Betriebsdaten
 *   2. sonst Benutzer mit Rolle Inhaber
 *   3. sonst Administratoren
 *   4. zuletzt die Plattform-Absenderadresse (mail.from)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class TenantNotifications
{
    /**
     * @return array<int, string>
     */
    public static function recipients(): array
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
