<?php

namespace App\Filament\App\Resources\Calibers\Pages;

use App\Filament\App\Resources\Calibers\CaliberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalibers extends ListRecords
{
    protected static string $resource = CaliberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
