<?php

namespace App\Filament\App\Resources\Auctions\Pages;

use App\Filament\App\Resources\Auctions\AuctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAuctions extends ListRecords
{
    protected static string $resource = AuctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
