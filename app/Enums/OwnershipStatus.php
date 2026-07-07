<?php

/**
 * =========================================================================
 * OwnershipStatus — Eigentumsverhältnis einer Uhr im Bestand
 * =========================================================================
 *
 * Zweck:
 *   Unterscheidet, WEM die Uhr gehört — unabhängig vom Bestandsstatus
 *   (WatchStatus). Eine Kommissionsuhr kann "an Lager" sein, gehört aber
 *   dem Einlieferer (owner_name/owner_address am Datensatz).
 *
 * Werte (Code Englisch, UI Deutsch — Projektregel):
 *   - Owned            : Eigenbestand des Betriebs
 *   - Commission       : Kommissionsware (Fremdeigentum im Verkauf)
 *   - CustomerProperty : Kundeneigentum (z. B. zur Reparatur/Schätzung im Haus)
 *
 * ACHTUNG: Persistierte Werte NIE umbenennen (Tenant-Datenbanken!).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OwnershipStatus: string implements HasColor, HasLabel
{
    case Owned = 'owned';
    case Commission = 'commission';
    case CustomerProperty = 'customer_property';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owned => 'Eigenbestand',
            self::Commission => 'Kommission',
            self::CustomerProperty => 'Kundeneigentum',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owned => 'success',
            self::Commission => 'primary',
            self::CustomerProperty => 'warning',
        };
    }
}
