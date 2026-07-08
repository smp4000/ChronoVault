<?php

/**
 * =========================================================================
 * ServiceRecord — Servicevorgang einer Uhr (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Ein Wartungs-/Reparaturvorgang (ServiceType) mit Status-Workflow
 *   (Offen → In Arbeit → Abgeschlossen), Werkstatt (Contact), Kosten
 *   und Service-Garantie. previous_watch_status merkt sich den
 *   Uhren-Status vor dem Einreichen — der Abschluss stellt ihn wieder her.
 *
 * Erstellung/Abschluss IMMER über die Actions (StartServiceAction /
 * CompleteServiceAction) — sie halten den Uhren-Status synchron.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Enums\WatchStatus;
use Database\Factories\ServiceRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRecord extends Model
{
    /** @use HasFactory<ServiceRecordFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'watch_id',
        'contact_id',
        'created_by_user_id',
        'type',
        'status',
        'previous_watch_status',
        'description',
        'cost',
        'currency',
        'submitted_at',
        'completed_at',
        'warranty_until',
        'document_number',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'status' => ServiceStatus::class,
            'previous_watch_status' => WatchStatus::class,
            'cost' => 'decimal:2',
            'submitted_at' => 'date',
            'completed_at' => 'date',
            'warranty_until' => 'date',
        ];
    }

    /**
     * Erfasser automatisch setzen (Tenant-Benutzer).
     */
    protected static function booted(): void
    {
        static::creating(function (ServiceRecord $record): void {
            $record->created_by_user_id ??= auth()->id();
        });
    }

    /**
     * @return BelongsTo<Watch, $this>
     */
    public function watch(): BelongsTo
    {
        return $this->belongsTo(Watch::class);
    }

    /**
     * Werkstatt/Dienstleister.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Abgeschlossen? (getAttribute — typsicher für statische Analyse.)
     */
    public function isCompleted(): bool
    {
        return $this->getAttribute('status') === ServiceStatus::Completed;
    }
}
