<?php

/**
 * =========================================================================
 * PriceProposalRequest — Validierung des Preisvorschlag-Formulars (Shop)
 * =========================================================================
 *
 * Zweck:
 *   Formale Prüfung des Preisvorschlags zu einer Uhr (Shop-Detailseite,
 *   Modal „Preis vorschlagen") mit deutschen Meldungen. Spam-Schutz über
 *   eine einfache Rechenfrage (a + b aus dem Formular) und die
 *   DSGVO-Einwilligung als Pflicht-Checkbox. Versand im ShopController
 *   (PriceProposalMail).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PriceProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proposed_price' => 'required|numeric|min:1|max:100000000',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'nullable|string|max:5000',
            'captcha_a' => 'required|integer|min:1|max:9',
            'captcha_b' => 'required|integer|min:1|max:9',
            'captcha' => [
                'required',
                'integer',
                // Rechenfrage: Antwort muss a + b entsprechen (Spam-Schutz)
                function (string $attribute, mixed $value, Closure $fail): void {
                    $expected = (int) $this->input('captcha_a') + (int) $this->input('captcha_b');

                    if ((int) $value !== $expected) {
                        $fail('Die Antwort auf die Sicherheitsfrage ist leider falsch.');
                    }
                },
            ],
            'privacy' => 'accepted',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proposed_price.required' => 'Bitte geben Sie Ihren Preisvorschlag an.',
            'proposed_price.numeric' => 'Bitte geben Sie den Preisvorschlag als Betrag in Euro an.',
            'proposed_price.min' => 'Bitte geben Sie einen Preisvorschlag über 0 € an.',
            'name.required' => 'Bitte geben Sie Ihren Namen an.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'message.max' => 'Ihre Nachricht ist zu lang (max. 5000 Zeichen).',
            'captcha.required' => 'Bitte beantworten Sie die Sicherheitsfrage.',
            'captcha.integer' => 'Bitte beantworten Sie die Sicherheitsfrage mit einer Zahl.',
            'privacy.accepted' => 'Bitte stimmen Sie der Verarbeitung Ihrer Angaben zu.',
        ];
    }
}
