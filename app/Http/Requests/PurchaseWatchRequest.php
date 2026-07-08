<?php

/**
 * =========================================================================
 * PurchaseWatchRequest — Verbindlicher Shop-Kauf (Käuferdaten)
 * =========================================================================
 *
 * Zweck:
 *   Formale Prüfung des Kaufformulars (deutsche Meldungen). Die
 *   fachlichen Regeln (Uhr noch verfügbar, Festpreis, Doppelkauf-Schutz)
 *   liegen in der PurchaseWatchAction.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseWatchRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'street' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'accept_binding' => 'accepted',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'last_name.required' => 'Bitte geben Sie Ihren Nachnamen an.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'street.required' => 'Bitte geben Sie Straße und Hausnummer an.',
            'postal_code.required' => 'Bitte geben Sie die Postleitzahl an.',
            'city.required' => 'Bitte geben Sie den Ort an.',
            'country.required' => 'Bitte geben Sie das Land an.',
            'accept_binding.accepted' => 'Bitte bestätigen Sie den verbindlichen Kauf.',
        ];
    }
}
