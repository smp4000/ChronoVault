<?php

/**
 * =========================================================================
 * SellerRegistrationRequest — Validierung der Verkäufer-Registrierung
 * =========================================================================
 *
 * Zweck:
 *   Formalvalidierung der Selbst-Registrierung auf dem zentralen
 *   Marktplatz (eBay-Prinzip: privat ODER gewerblich). Prüft neben den
 *   Stammdaten die Wunsch-URL (Slug: Format, Verfügbarkeit, reservierte
 *   Begriffe), die Rechenfrage (Spam-Schutz wie beim Preisvorschlag)
 *   und die DSGVO-Einwilligung.
 *
 * Nutzung: SellerRegistrationController::store.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SellerRegistrationRequest extends FormRequest
{
    /** Subdomains, die nie an Verkäufer vergeben werden (Technik/Plattform). */
    private const RESERVED_SLUGS = [
        'www', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'api', 'app', 'admin',
        'shop', 'blog', 'hilfe', 'help', 'support', 'status', 'cdn',
        'marktplatz', 'marketplace', 'chrono', 'chronosave', 'chrono-save',
        'chronovault', 'test', 'demo', 'staging',
    ];

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
            'seller_type' => ['required', 'in:private,commercial'],
            'shop_name' => ['required', 'string', 'min:3', 'max:60'],
            'slug' => [
                'nullable',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array((string) $value, self::RESERVED_SLUGS, true)) {
                        $fail('Diese Wunsch-Adresse ist reserviert — bitte wählen Sie eine andere.');

                        return;
                    }

                    if (Tenant::withTrashed()->where('slug', $value)->exists()) {
                        $fail('Diese Wunsch-Adresse ist bereits vergeben — bitte wählen Sie eine andere.');
                    }
                },
            ],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'captcha_a' => ['required', 'integer'],
            'captcha_b' => ['required', 'integer'],
            'captcha' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((int) $value !== (int) $this->input('captcha_a') + (int) $this->input('captcha_b')) {
                        $fail('Die Rechenaufgabe wurde falsch beantwortet — bitte erneut versuchen.');
                    }
                },
            ],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seller_type.required' => 'Bitte wählen Sie, ob Sie privat oder gewerblich verkaufen.',
            'seller_type.in' => 'Bitte wählen Sie, ob Sie privat oder gewerblich verkaufen.',
            'shop_name.required' => 'Bitte geben Sie einen Namen für Ihre Verkaufsseite an.',
            'shop_name.min' => 'Der Name muss mindestens 3 Zeichen lang sein.',
            'slug.regex' => 'Die Wunsch-Adresse darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten (z. B. mueller-uhren).',
            'slug.min' => 'Die Wunsch-Adresse muss mindestens 3 Zeichen lang sein.',
            'owner_name.required' => 'Bitte geben Sie Ihren Namen an.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'password.required' => 'Bitte vergeben Sie ein Passwort.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
            'privacy.accepted' => 'Bitte stimmen Sie der Datenverarbeitung zu.',
        ];
    }
}
