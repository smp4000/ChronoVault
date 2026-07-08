<?php

/**
 * =========================================================================
 * CompleteServiceAction — Servicevorgang abschließen
 * =========================================================================
 *
 * Zweck:
 *   Setzt den Vorgang auf "Abgeschlossen" (completed_at, optional Kosten/
 *   Garantie) und stellt den gemerkten Uhren-Status wieder her (Fallback:
 *   "An Lager"). Restore nur, wenn die Uhr noch "Im Service" steht —
 *   ein zwischenzeitlicher Verkauf o. Ä. wird nicht überschrieben.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\ServiceStatus;
use App\Enums\WatchStatus;
use App\Models\ServiceRecord;

class CompleteServiceAction
{
    /**
     * @param  array{completed_at?: string|\DateTimeInterface|null, cost?: float|string|null, warranty_until?: string|\DateTimeInterface|null, notes?: string|null}  $data
     */
    public function execute(ServiceRecord $record, array $data = []): ServiceRecord
    {
        $record->fill([
            'status' => ServiceStatus::Completed,
            'completed_at' => $data['completed_at'] ?? now(),
        ]);

        if (array_key_exists('cost', $data) && $data['cost'] !== null) {
            $record->cost = $data['cost'];
        }

        if (array_key_exists('warranty_until', $data)) {
            $record->warranty_until = $data['warranty_until'];
        }

        if (array_key_exists('notes', $data) && filled($data['notes'])) {
            $record->notes = $data['notes'];
        }

        $record->save();

        $watch = $record->watch;

        if ($watch->getAttribute('status') === WatchStatus::InService) {
            $watch->forceFill([
                'status' => $record->getAttribute('previous_watch_status') ?? WatchStatus::InStock,
            ])->saveQuietly();
        }

        return $record;
    }
}
