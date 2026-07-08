<?php

/**
 * =========================================================================
 * PaymentMethod — Zahlungsart eines Kauf-/Verkaufsvorgangs
 * =========================================================================
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Paypal = 'paypal';
    case Financing = 'financing';
    case TradeIn = 'trade_in';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Barzahlung',
            self::BankTransfer => 'Überweisung',
            self::Card => 'Kartenzahlung',
            self::Paypal => 'PayPal',
            self::Financing => 'Finanzierung',
            self::TradeIn => 'Inzahlungnahme',
            self::Other => 'Sonstige',
        };
    }
}
