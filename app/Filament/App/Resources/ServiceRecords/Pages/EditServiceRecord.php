<?php

namespace App\Filament\App\Resources\ServiceRecords\Pages;

use App\Filament\App\Resources\ServiceRecords\ServiceRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceRecord extends EditRecord
{
    protected static string $resource = ServiceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
