<?php

/**
 * =========================================================================
 * QrPng — Allgemeiner QR-Code-Generator (PNG)
 * =========================================================================
 *
 * Zweck:
 *   Erzeugt ein QR-PNG für beliebige Inhalte (z. B. den signierten Link
 *   zur mobilen Foto-Upload-Seite). Der GiroCode (EPC-Zahlungs-QR) hat
 *   weiterhin seine eigene Klasse mit dem EPC069-12-Payload.
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

class QrPng
{
    /**
     * QR-PNG (Binärdaten) für beliebigen Text/URL.
     */
    public static function make(string $data, int $size = 320): string
    {
        $builder = new Builder(
            writer: new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 12,
        );

        return $builder->build()->getString();
    }
}
