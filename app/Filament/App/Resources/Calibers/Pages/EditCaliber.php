<?php

namespace App\Filament\App\Resources\Calibers\Pages;

use App\Filament\App\Resources\Calibers\CaliberResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCaliber extends EditRecord
{
    protected static string $resource = CaliberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
