<?php

/**
 * =========================================================================
 * WatchInquiryRequest — Validierung des Shop-Anfrageformulars
 * =========================================================================
 *
 * Zweck:
 *   Formale Prüfung der Kaufanfrage zu einer Uhr (Shop-Detailseite)
 *   mit deutschen Meldungen. Der Versand an den Händler läuft im
 *   ShopController (WatchInquiryMail).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WatchInquiryRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:5000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Bitte geben Sie Ihren Namen an.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'message.required' => 'Bitte beschreiben Sie kurz Ihr Anliegen.',
            'message.max' => 'Ihre Nachricht ist zu lang (max. 5000 Zeichen).',
        ];
    }
}
