<?php

/**
 * =========================================================================
 * PlaceBidRequest — Validierung des öffentlichen Gebotsformulars
 * =========================================================================
 *
 * Zweck:
 *   Formale Prüfung (Pflichtfelder, Formate) mit deutschen Meldungen.
 *   Die FACHLICHEN Regeln (Bietfenster, Mindestgebot, Race-Schutz)
 *   liegen in der PlaceBidAction — nicht hier.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Öffentliches Formular — die Bietbarkeit prüft die Action.
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'bidder_name' => 'required|string|max:255',
            'bidder_email' => 'required|email|max:255',
            'bidder_phone' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:1|max:99999999',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bidder_name.required' => 'Bitte geben Sie Ihren Namen an.',
            'bidder_email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'bidder_email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'amount.required' => 'Bitte geben Sie Ihr Gebot an.',
            'amount.numeric' => 'Das Gebot muss eine Zahl sein.',
            'amount.min' => 'Das Gebot muss mindestens 1 € betragen.',
            'amount.max' => 'Das Gebot übersteigt den zulässigen Höchstwert.',
        ];
    }
}
