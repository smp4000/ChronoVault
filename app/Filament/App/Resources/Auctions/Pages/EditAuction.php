<?php

namespace App\Filament\App\Resources\Auctions\Pages;

use App\Filament\App\Resources\Auctions\AuctionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAuction extends EditRecord
{
    protected static string $resource = AuctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
