<?php

/**
 * =========================================================================
 * Contact — Kontakt im Kundenstamm (Tenant-Datenbank)
 * =========================================================================
 *
 * Zweck:
 *   Käufer, Verkäufer/Lieferanten und Einlieferer eines Betriebs —
 *   Privatpersonen wie Firmen (company_name ODER first/last_name).
 *   Wird von Transaktionen referenziert (Modul 5); später auch von
 *   Kommission (owner) und Servicevorgängen (Modul 6).
 *
 * Verantwortlichkeiten:
 *   - displayName(): einheitliche Anzeige (Firma bzw. "Vorname Nachname")
 *   - Beziehung zu Transaktionen (Kaufhistorie des Kontakts)
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactType;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'street',
        'postal_code',
        'city',
        'country',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
        ];
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Servicevorgänge, bei denen dieser Kontakt die Werkstatt ist (Modul 6).
     *
     * @return HasMany<ServiceRecord, $this>
     */
    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class);
    }

    /**
     * Auktionslose, bei denen dieser Kontakt der Käufer ist (Modul 8).
     *
     * @return HasMany<AuctionLot, $this>
     */
    public function auctionLots(): HasMany
    {
        return $this->hasMany(AuctionLot::class, 'buyer_contact_id');
    }

    /**
     * Anzeigename: Firma — sonst "Vorname Nachname".
     */
    public function displayName(): string
    {
        if (filled($this->company_name)) {
            return (string) $this->company_name;
        }

        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
