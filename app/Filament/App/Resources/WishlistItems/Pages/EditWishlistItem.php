<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\WishlistItems\Pages;

use App\Filament\App\Resources\WishlistItems\WishlistItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditWishlistItem extends EditRecord
{
    protected static string $resource = WishlistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
