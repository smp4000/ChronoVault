<?php

/**
 * =========================================================================
 * ListPriceProposals — Listenseite der Preisvorschläge
 * =========================================================================
 * Kein Anlegen im Panel — Vorschläge kommen aus dem öffentlichen Shop.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\PriceProposals\Pages;

use App\Filament\App\Resources\PriceProposals\PriceProposalResource;
use Filament\Resources\Pages\ListRecords;

class ListPriceProposals extends ListRecords
{
    protected static string $resource = PriceProposalResource::class;
}
