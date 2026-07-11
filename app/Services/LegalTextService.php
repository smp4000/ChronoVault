<?php

/**
 * =========================================================================
 * LegalTextService — KI-Entwürfe für Impressum/Datenschutz/Widerruf
 * =========================================================================
 *
 * Zweck:
 *   Generiert aus den Angaben des Händlers (Fragen-Dialog in den
 *   Betriebsdaten) deutsche Rechtstext-ENTWÜRFE für die Shop-Seiten.
 *   Die Plattform-Fakten (gebrauchte Uhren, nur technisch notwendige
 *   Cookies, Hosting in Deutschland, Cloudflare, Mail-Versand) fließen
 *   fest in den Prompt ein.
 *
 * WICHTIG: KI-Entwurf ohne Rechtsgewähr — der Dialog weist darauf hin,
 * dass die Texte geprüft werden sollten (keine Rechtsberatung).
 *
 * Provider wie ProposalReplyService: Perplexity bevorzugt (ohne
 * Web-Suche), Anthropic Claude als Fallback.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LegalTextService
{
    private const ANTHROPIC_MODEL = 'claude-opus-4-8';

    public function __construct(private ?Client $client = null) {}

    /**
     * Entwirft den Rechtstext.
     *
     * @param  'imprint'|'privacy'|'revocation'  $type
     * @param  array<string, mixed>  $answers
     *
     * @throws RuntimeException ohne API-Key oder bei Provider-Fehlern
     */
    public function generate(string $type, array $answers): string
    {
        set_time_limit(120);

        $prompt = $this->buildPrompt($type, $answers);

        if ($this->client !== null) {
            return $this->viaAnthropic($this->client, $prompt);
        }

        $perplexityKey = config('services.perplexity.api_key');

        if (is_string($perplexityKey) && $perplexityKey !== '') {
            return $this->viaPerplexity($perplexityKey, $prompt);
        }

        return $this->viaAnthropic($this->makeClient(), $prompt);
    }

    private function viaPerplexity(string $apiKey, string $prompt): string
    {
        $response = Http::withToken($apiKey)
            ->timeout(100)
            ->post('https://api.perplexity.ai/chat/completions', [
                'model' => (string) config('services.perplexity.model', 'sonar-pro'),
                'disable_search' => true,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'KI-Anfrage fehlgeschlagen (HTTP '.$response->status().'). API-Key und Guthaben prüfen.'
            );
        }

        $text = trim((string) $response->json('choices.0.message.content', ''));

        if ($text === '') {
            throw new RuntimeException('Die KI hat keinen Entwurf geliefert — bitte erneut versuchen.');
        }

        return $text;
    }

    private function viaAnthropic(Client $client, string $prompt): string
    {
        $response = $client->messages->create(
            maxTokens: 4096,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: self::ANTHROPIC_MODEL,
            system: $this->systemPrompt(),
        );

        $text = '';

        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Die KI hat keinen Entwurf geliefert — bitte erneut versuchen.');
        }

        return $text;
    }

    private function systemPrompt(): string
    {
        return 'Du bist Experte für deutsche E-Commerce-Rechtstexte (Impressum nach § 5 DDG, '
            .'Datenschutzerklärung nach DSGVO, Widerrufsbelehrung nach EGBGB-Muster). '
            .'Antworte AUSSCHLIESSLICH mit dem reinen Rechtstext — ohne Markdown-Formatierung, '
            .'ohne Platzhalter in eckigen Klammern, ohne Begleitkommentare oder Disclaimer. '
            .'Gliedere mit Überschriften in GROSSBUCHSTABEN oder nummerierten Abschnitten und '
            .'Leerzeilen. Verwende ausschließlich die übergebenen Angaben — erfinde nichts dazu.';
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function buildPrompt(string $type, array $answers): string
    {
        $lines = [
            'Angaben zum Betrieb:',
            'Firma/Name: '.($answers['company_name'] ?? ''),
            'Rechtsform: '.($answers['legal_form'] ?? 'Einzelunternehmen'),
            'Inhaber/Vertretungsberechtigter: '.($answers['owner_name'] ?? ''),
            'Anschrift: '.($answers['street'] ?? '').', '.($answers['postal_code'] ?? '').' '.($answers['city'] ?? ''),
            'E-Mail: '.($answers['email'] ?? ''),
        ];

        if (filled($answers['phone'] ?? null)) {
            $lines[] = 'Telefon: '.$answers['phone'];
        }

        if (filled($answers['vat_id'] ?? null)) {
            $lines[] = 'USt-IdNr.: '.$answers['vat_id'];
        }

        if (filled($answers['tax_number'] ?? null)) {
            $lines[] = 'Steuernummer: '.$answers['tax_number'];
        }

        if (filled($answers['register'] ?? null)) {
            $lines[] = 'Registereintrag: '.$answers['register'];
        }

        $lines[] = '';
        $lines[] = 'Fakten zur Plattform (Online-Shop für gebrauchte Luxusuhren):';
        $lines[] = '- Verkauf gebrauchter Uhren an Verbraucher und Unternehmer, Zahlung per Überweisung (Vorkasse)';
        $lines[] = '- Online-Auktionen mit verbindlichen Geboten';
        $lines[] = '- Formulare: Kaufanfragen, Preisvorschläge, Sofortkauf mit Lieferadresse, Auktionsgebote (Name, E-Mail, ggf. Telefon/Adresse, IP-Adresse bei Geboten)';
        $lines[] = '- Cookies: AUSSCHLIESSLICH technisch notwendige (Session, CSRF) — kein Tracking, keine Analyse-Tools, keine Werbe-Cookies';
        $lines[] = '- Hosting: Hetzner Online GmbH (Rechenzentrum in Deutschland); Cloudflare als CDN/Schutz vorgeschaltet';
        $lines[] = '- E-Mail-Versand über einen deutschen Mail-Anbieter';

        if (filled($answers['extras'] ?? null)) {
            $lines[] = '- Besonderheiten laut Betreiber: '.$answers['extras'];
        }

        $lines[] = '';
        $lines[] = match ($type) {
            'imprint' => 'Erstelle daraus ein vollständiges deutsches IMPRESSUM nach § 5 DDG und § 18 Abs. 2 MStV '
                .'(inkl. Verantwortlicher für den Inhalt, Verbraucherstreitbeilegung/OS-Plattform-Hinweis).',
            'privacy' => 'Erstelle daraus eine vollständige deutsche DATENSCHUTZERKLÄRUNG nach DSGVO für diesen Shop: '
                .'Verantwortlicher, Hosting (Hetzner, Auftragsverarbeitung) und Cloudflare, Server-Logs, technisch '
                .'notwendige Cookies (keine Einwilligung nötig), Datenverarbeitung bei Anfragen/Preisvorschlägen/'
                .'Käufen/Auktionsgeboten (Zwecke, Rechtsgrundlagen Art. 6 DSGVO, Speicherdauern inkl. gesetzlicher '
                .'Aufbewahrungsfristen für Rechnungen), E-Mail-Kommunikation, Betroffenenrechte (Auskunft, '
                .'Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerderecht).',
            default => 'Erstelle daraus eine vollständige deutsche WIDERRUFSBELEHRUNG für Verbraucher im Fernabsatz '
                .'nach dem gesetzlichen Muster (14 Tage, Folgen des Widerrufs, Rücksendekosten trägt der Käufer, '
                .'Muster-Widerrufsformular am Ende).',
        };

        return implode("\n", $lines);
    }

    private function makeClient(): Client
    {
        $apiKey = config('services.anthropic.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException(
                'KI-Rechtstexte sind nicht konfiguriert: PERPLEXITY_API_KEY (oder ANTHROPIC_API_KEY) in der .env hinterlegen.'
            );
        }

        return new Client(apiKey: $apiKey);
    }
}
