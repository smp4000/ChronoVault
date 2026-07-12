<?php

/**
 * =========================================================================
 * SellerRegistrationController — Selbst-Registrierung von Verkäufern
 * =========================================================================
 *
 * Zweck:
 *   „Jetzt verkaufen" auf dem zentralen Marktplatz (eBay-Prinzip):
 *   Privatpersonen UND Händler registrieren sich selbst, bekommen ihre
 *   eigene Verkaufsseite ({slug}.chrono-save.de) samt Panel und können
 *   sofort Uhren einstellen. Provisionierung läuft komplett über die
 *   bestehende CreateTenantAction (Tenant + DB + Domain + Owner).
 *
 * Verantwortlichkeiten:
 *   - Formular anzeigen (create) und Registrierung ausführen (store).
 *   - Missbrauchsschutz: Rechenfrage (Request) + Throttle (Route) —
 *     jede Registrierung legt schließlich eine echte Datenbank an.
 *
 * Erweiterungen: Willkommens-Mail, E-Mail-Verifizierung, Pläne/Billing.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateTenantAction;
use App\Http\Requests\SellerRegistrationRequest;
use Illuminate\Contracts\View\View;
use Throwable;

class SellerRegistrationController extends Controller
{
    /** Registrierungsformular („Jetzt verkaufen"). */
    public function create(): View
    {
        return view('marketplace.register', [
            'capA' => random_int(1, 9),
            'capB' => random_int(1, 9),
        ]);
    }

    /** Registrierung ausführen und Erfolgsseite mit den Zugängen zeigen. */
    public function store(SellerRegistrationRequest $request): View
    {
        $data = $request->validated();

        // Privatverkäufer: Seitenname = eigener Name, Adresse wird daraus
        // erzeugt (TenantObserver) — sie füllen keine Geschäftsfelder aus.
        $isPrivate = $data['seller_type'] === 'private';

        try {
            $tenant = app(CreateTenantAction::class)->execute(
                name: $isPrivate ? $data['owner_name'] : (string) $data['shop_name'],
                ownerName: $data['owner_name'],
                ownerEmail: strtolower(trim($data['email'])),
                ownerPassword: $data['password'],
                slug: $isPrivate ? null : ($data['slug'] ?? null),
                sellerType: $data['seller_type'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return view('marketplace.register', [
                'capA' => random_int(1, 9),
                'capB' => random_int(1, 9),
            ])->with('registration_error', 'Die Registrierung ist fehlgeschlagen — bitte versuchen Sie es erneut oder kontaktieren Sie uns.');
        }

        $scheme = app()->isProduction() ? 'https://' : 'http://';
        $domain = $tenant->primaryDomain();

        return view('marketplace.registered', [
            'shopName' => (string) $tenant->getAttribute('name'),
            'sellerType' => $data['seller_type'],
            'shopUrl' => $scheme.$domain,
            'panelUrl' => $scheme.$domain.'/app',
            'email' => strtolower(trim($data['email'])),
        ]);
    }
}
