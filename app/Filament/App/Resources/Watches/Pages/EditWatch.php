<?php

namespace App\Filament\App\Resources\Watches\Pages;

use App\Filament\App\Resources\Watches\WatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditWatch extends EditRecord
{
    protected static string $resource = WatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
