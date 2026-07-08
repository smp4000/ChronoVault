<?php

/**
 * =========================================================================
 * GiroCode — EPC-QR-Code für SEPA-Überweisungen (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Erzeugt den standardisierten EPC069-12-Payload („GiroCode") und
 *   das QR-PNG für die Zuschlag-Mail: Der Gewinner scannt den Code mit
 *   seiner Banking-App — Empfänger, IBAN, Betrag und Verwendungszweck
 *   sind vorausgefüllt.
 *
 * Format (Version 002, UTF-8, Zeilen mit \n):
 *   BCD / 002 / 1 / SCT / BIC / Name / IBAN / EUR<Betrag> /
 *   <Purpose leer> / <strukturierte Referenz leer> / Verwendungszweck
 *
 * Abhängigkeiten: endroid/qr-code (PNG via GD).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class GiroCode
{
    /**
     * EPC-Payload für eine SEPA-Überweisung.
     */
    public static function payload(
        string $accountHolder,
        string $iban,
        ?string $bic,
        float $amount,
        string $remittance,
    ): string {
        return implode("\n", [
            'BCD',
            '002',
            '1', // UTF-8
            'SCT',
            strtoupper($bic ?? ''),
            mb_substr($accountHolder, 0, 70),
            strtoupper(str_replace(' ', '', $iban)),
            'EUR'.number_format($amount, 2, '.', ''),
            '', // Purpose-Code
            '', // strukturierte Referenz
            mb_substr($remittance, 0, 140),
        ]);
    }

    /**
     * QR-PNG (Binärdaten) für den Payload — wird als cid-Anhang in die
     * Mail eingebettet (data-URIs blockieren viele Mail-Clients).
     */
    public static function png(
        string $accountHolder,
        string $iban,
        ?string $bic,
        float $amount,
        string $remittance,
    ): string {
        $builder = new Builder(
            writer: new PngWriter,
            data: self::payload($accountHolder, $iban, $bic, $amount, $remittance),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 320,
            margin: 12,
        );

        return $builder->build()->getString();
    }
}
