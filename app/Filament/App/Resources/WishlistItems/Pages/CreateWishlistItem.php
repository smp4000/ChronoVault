<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\WishlistItems\Pages;

use App\Filament\App\Resources\WishlistItems\WishlistItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWishlistItem extends CreateRecord
{
    protected static string $resource = WishlistItemResource::class;
}
