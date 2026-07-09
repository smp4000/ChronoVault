<?php

/**
 * =========================================================================
 * ProposalReplyService — KI-Antwortentwurf für Preisvorschläge
 * =========================================================================
 *
 * Zweck:
 *   Erstellt im Antworten-Dialog der Preisvorschläge (Panel) einen
 *   deutschen Antwortentwurf per KI: Kontext sind Uhr, Wunschpreis,
 *   Angebotspreis, Kundennachricht sowie Tenor + Stichpunkte des
 *   Händlers. Der Entwurf landet im Formular und wird vom Händler
 *   geprüft/angepasst — die KI versendet NIE selbst.
 *
 * Provider (wie WatchReferenceLookupService):
 *   1. Perplexity (sonar-pro) — bevorzugt (Key des Auftraggebers)
 *   2. Anthropic Claude (claude-opus-4-8) — Fallback
 *
 * Fehler werfen RuntimeExceptions mit deutscher Meldung — die UI zeigt
 * sie als Notification.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use App\Models\PriceProposal;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProposalReplyService
{
    private const ANTHROPIC_MODEL = 'claude-opus-4-8';

    /** Tenor-Optionen für den Antworten-Dialog (Wert => Label). */
    public const TONES = [
        'thanks' => 'Bedanken & Rückfrage stellen',
        'negotiate' => 'Verhandlungsbereitschaft signalisieren',
        'firm' => 'Freundlich beim Preis bleiben',
        'decline' => 'Höflich ablehnen',
    ];

    public function __construct(private ?Client $client = null) {}

    /**
     * Entwirft die Antwort auf einen Preisvorschlag.
     *
     * @throws RuntimeException ohne API-Key oder bei Provider-Fehlern
     */
    public function draft(PriceProposal $proposal, string $tone, ?string $keyPoints = null): string
    {
        set_time_limit(120);

        $prompt = $this->buildPrompt($proposal, $tone, $keyPoints);

        // Injizierter Anthropic-Client (Tests) hat Vorrang.
        if ($this->client !== null) {
            return $this->draftViaAnthropic($this->client, $prompt);
        }

        $perplexityKey = config('services.perplexity.api_key');

        if (is_string($perplexityKey) && $perplexityKey !== '') {
            return $this->draftViaPerplexity($perplexityKey, $prompt);
        }

        return $this->draftViaAnthropic($this->makeClient(), $prompt);
    }

    private function draftViaPerplexity(string $apiKey, string $prompt): string
    {
        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.perplexity.ai/chat/completions', [
                'model' => (string) config('services.perplexity.model', 'sonar-pro'),
                // Keine Web-Suche nötig — reine Textaufgabe
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

    private function draftViaAnthropic(Client $client, string $prompt): string
    {
        $response = $client->messages->create(
            maxTokens: 1024,
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
        return 'Du schreibst als erfahrener, freundlicher Uhrenhändler professionelle '
            .'deutsche Kunden-E-Mails. Antworte AUSSCHLIESSLICH mit dem reinen E-Mail-Text '
            .'(Anrede bis Grußformel) — ohne Betreff, ohne Platzhalter in eckigen Klammern, '
            .'ohne Markdown, ohne Begleitkommentar. Kurz, wertschätzend, verbindlich im Ton. '
            .'Nenne NIEMALS interne Informationen wie Einkaufspreise oder Limits.';
    }

    private function buildPrompt(PriceProposal $proposal, string $tone, ?string $keyPoints): string
    {
        $watch = $proposal->watch;

        $toneInstruction = match ($tone) {
            'thanks' => 'Bedanke dich für das Interesse und stelle eine höfliche Rückfrage (z. B. zur Vorstellung des Kunden oder zu einer Besichtigung).',
            'negotiate' => 'Signalisiere Verhandlungsbereitschaft, ohne bereits einen konkreten neuen Preis zu nennen — lade zu einem Gespräch ein.',
            'firm' => 'Bleibe freundlich, aber klar beim aktuellen Angebotspreis und begründe kurz den Wert der Uhr (Zustand, Lieferumfang, Service).',
            default => 'Lehne den Vorschlag höflich und wertschätzend ab und lade ein, bei anderen Uhren in Kontakt zu bleiben.',
        };

        $lines = [
            'Schreibe die Antwort auf folgenden Preisvorschlag eines Kunden:',
            'Händler: '.((string) tenant('name')),
            'Uhr: '.($watch?->fullName() ?? 'unbekannt'),
            'Angebotspreis im Shop: '.($proposal->asking_price_at_time !== null ? number_format((float) $proposal->asking_price_at_time, 0, ',', '.').' €' : 'auf Anfrage'),
            'Wunschpreis des Kunden: '.number_format((float) $proposal->proposed_price, 0, ',', '.').' €',
            'Kundenname: '.$proposal->name,
        ];

        if (filled($proposal->message)) {
            $lines[] = 'Nachricht des Kunden: "'.$proposal->message.'"';
        }

        $lines[] = 'Gewünschter Tenor: '.$toneInstruction;

        if (filled($keyPoints)) {
            $lines[] = 'Diese Punkte des Händlers unbedingt einarbeiten: '.$keyPoints;
        }

        $lines[] = 'Unterschreibe mit "Mit freundlichen Grüßen" und dem Händlernamen.';

        return implode("\n", $lines);
    }

    private function makeClient(): Client
    {
        $apiKey = config('services.anthropic.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException(
                'KI-Antwort ist nicht konfiguriert: PERPLEXITY_API_KEY (oder ANTHROPIC_API_KEY) in der .env hinterlegen.'
            );
        }

        return new Client(apiKey: $apiKey);
    }
}
