<?php

/**
 * =========================================================================
 * WinnerDetailsRequest — Liefer-/Rechnungsdaten des Auktionsgewinners
 * =========================================================================
 *
 * Zweck:
 *   Formale Prüfung des Gewinner-Formulars (signierter Link aus der
 *   Zuschlag-Mail) mit deutschen Meldungen. Die Daten aktualisieren
 *   den beim Zuschlag angelegten Käufer-Kontakt.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WinnerDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Zugriffsschutz übernimmt die signed-Middleware der Route.
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'street' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'last_name.required' => 'Bitte geben Sie Ihren Nachnamen an.',
            'street.required' => 'Bitte geben Sie Straße und Hausnummer an.',
            'postal_code.required' => 'Bitte geben Sie die Postleitzahl an.',
            'city.required' => 'Bitte geben Sie den Ort an.',
            'country.required' => 'Bitte geben Sie das Land an.',
        ];
    }
}
