<?php

/**
 * =========================================================================
 * PhotoSlot — Slots des geführten Foto-Uploads (Chrono24-Vorbild)
 * =========================================================================
 *
 * Zweck:
 *   Definiert die Standard-Perspektiven, die ein gutes Inserat braucht.
 *   Jeder Slot ist ein eigenes Upload-Feld (WatchForm); das Foto wird in
 *   der photos-Media-Collection mit custom_property "slot" abgelegt.
 *   Übernahme des photo_slots-Konzepts aus der Vorgänger-Anwendung.
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (custom_properties!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PhotoSlot: string implements HasLabel
{
    case Front = 'front';
    case Back = 'back';
    case SideCrown = 'side_crown';
    case Clasp = 'clasp';
    case Bracelet = 'bracelet';
    case FullSet = 'full_set';

    public function getLabel(): string
    {
        return match ($this) {
            self::Front => 'Vorderseite',
            self::Back => 'Rückseite/Gehäuseboden',
            self::SideCrown => 'Seite & Krone',
            self::Clasp => 'Schließe',
            self::Bracelet => 'Armband',
            self::FullSet => 'Lieferumfang (Box & Papiere)',
        };
    }
}
