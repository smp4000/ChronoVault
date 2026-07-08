<?php

/**
 * =========================================================================
 * StartServiceAction — Uhr in den Service geben
 * =========================================================================
 *
 * Zweck:
 *   Legt den Servicevorgang an, MERKT sich den aktuellen Uhren-Status
 *   (previous_watch_status) und setzt die Uhr auf "Im Service".
 *   Die CompleteServiceAction stellt den gemerkten Status wieder her —
 *   eine Kommissionsuhr kommt als Kommission zurück.
 *
 * Aufrufer: Filament ("In Service geben"-Schnellaktion, RelationManager,
 * CreateServiceRecord-Page).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\ServiceStatus;
use App\Enums\WatchStatus;
use App\Models\ServiceRecord;
use App\Models\Watch;

class StartServiceAction
{
    /**
     * @param  array{type: string, contact_id?: string|null, description?: string|null, cost?: float|string|null, submitted_at?: string|\DateTimeInterface|null, document_number?: string|null, notes?: string|null}  $data
     */
    public function execute(Watch $watch, array $data): ServiceRecord
    {
        $record = $watch->serviceRecords()->create([
            'type' => $data['type'],
            'status' => ServiceStatus::InProgress,
            // Aktuellen Status merken — außer die Uhr ist schon im Service
            // (dann würden wir "in_service" als Restore-Ziel speichern).
            'previous_watch_status' => $watch->getAttribute('status') === WatchStatus::InService
                ? null
                : $watch->getAttribute('status'),
            'contact_id' => $data['contact_id'] ?? null,
            'description' => $data['description'] ?? null,
            'cost' => $data['cost'] ?? null,
            'submitted_at' => $data['submitted_at'] ?? now(),
            'document_number' => $data['document_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // saveQuietly: kein Observer-Durchlauf nötig.
        $watch->forceFill(['status' => WatchStatus::InService])->saveQuietly();

        return $record;
    }
}
