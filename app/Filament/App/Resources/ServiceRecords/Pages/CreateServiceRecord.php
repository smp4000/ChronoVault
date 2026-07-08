<?php

/**
 * =========================================================================
 * CreateServiceRecord — Anlage über die StartServiceAction
 * =========================================================================
 *
 * WARUM handleRecordCreation überschrieben:
 *   Das Anlegen setzt die Uhr auf "Im Service" und merkt sich den
 *   vorherigen Status — Logik der StartServiceAction, nicht der UI.
 * =========================================================================
 */

namespace App\Filament\App\Resources\ServiceRecords\Pages;

use App\Actions\Services\StartServiceAction;
use App\Filament\App\Resources\ServiceRecords\ServiceRecordResource;
use App\Models\Watch;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceRecord extends CreateRecord
{
    protected static string $resource = ServiceRecordResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $watch = Watch::findOrFail($data['watch_id']);

        return app(StartServiceAction::class)->execute($watch, $data);
    }
}
