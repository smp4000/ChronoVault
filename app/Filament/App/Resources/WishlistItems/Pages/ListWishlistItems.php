<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\WishlistItems\Pages;

use App\Filament\App\Resources\WishlistItems\WishlistItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWishlistItems extends ListRecords
{
    protected static string $resource = WishlistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
