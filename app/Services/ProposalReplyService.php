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
     * Entwirft die komplette Antwort-Mail auf einen Preisvorschlag
     * (Antworten-Dialog: Anrede bis Grußformel).
     *
     * @throws RuntimeException ohne API-Key oder bei Provider-Fehlern
     */
    public function draft(PriceProposal $proposal, string $tone, ?string $keyPoints = null): string
    {
        return $this->generate($this->buildPrompt($proposal, $tone, $keyPoints));
    }

    /**
     * Entwirft den Text-Baustein für die Dialoge Annehmen (persönliche
     * Ergänzung der Zusage-Mail), Ablehnen (Absage-Text) und
     * Gegenangebot (Einleitungstext) — jeweils OHNE Anrede/Grußformel,
     * die setzen die Mails automatisch.
     *
     * @param  'accept'|'decline'|'counter'  $intent
     *
     * @throws RuntimeException ohne API-Key oder bei Provider-Fehlern
     */
    public function draftForIntent(
        PriceProposal $proposal,
        string $intent,
        ?float $counterPrice = null,
        ?float $shippingPrice = null,
    ): string {
        return $this->generate($this->buildIntentPrompt($proposal, $intent, $counterPrice, $shippingPrice));
    }

    /**
     * Provider-Auswahl (Perplexity bevorzugt, Anthropic-Fallback).
     */
    private function generate(string $prompt): string
    {
        set_time_limit(120);

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
            .'deutsche Kunden-E-Mails. Antworte AUSSCHLIESSLICH mit dem reinen Text — '
            .'ohne Betreff, ohne Platzhalter in eckigen Klammern, ohne Markdown, '
            .'ohne Begleitkommentar. Kurz, wertschätzend, verbindlich im Ton. '
            .'WICHTIG zur Formatierung: Gliedere in KURZE Absätze (2–4 Sätze), '
            .'getrennt durch je eine Leerzeile — niemals ein einziger Textblock. '
            .'Nenne NIEMALS interne Informationen wie Einkaufspreise oder Limits.';
    }

    /**
     * Prompt für die Dialog-Bausteine (Annehmen/Ablehnen/Gegenangebot).
     */
    private function buildIntentPrompt(PriceProposal $proposal, string $intent, ?float $counterPrice, ?float $shippingPrice): string
    {
        $lines = $this->contextLines($proposal);

        $lines[] = match ($intent) {
            'accept' => 'Der Händler NIMMT den Preisvorschlag AN — der Kauf kommt zum Wunschpreis zustande. '
                .'Schreibe 2–3 herzliche Sätze als persönliche Ergänzung für die Zusage-Mail '
                .'(Freude über die Einigung, gute Wahl, Vorfreude aufs Versenden). '
                .'OHNE Anrede und OHNE Grußformel — beides setzt die Mail automatisch.',
            'counter' => 'Der Händler macht ein GEGENANGEBOT'
                .($counterPrice !== null ? ' über '.number_format($counterPrice, 0, ',', '.').' € für die Uhr' : '')
                .($shippingPrice !== null && $shippingPrice > 0 ? ' zzgl. '.number_format($shippingPrice, 2, ',', '.').' € versichertem Versand' : '')
                .'. Schreibe den Einleitungstext der Gegenangebots-Mail: Beginne klein geschrieben '
                .'(die Anrede „Guten Tag …," steht bereits davor), 1–2 kurze Absätze mit Leerzeile, '
                .'begründe den Preis wertschätzend (Zustand, Lieferumfang, Service/Prüfung), '
                .'und ende mit einer Überleitung wie „daher machen wir Ihnen gerne dieses Angebot:". '
                .'Die Preisaufstellung zeigt die Mail direkt darunter — nenne die Angebotszahlen NICHT selbst. '
                .'KEINE Grußformel.',
            default => 'Der Händler LEHNT den Preisvorschlag freundlich AB. '
                .'Schreibe den Text der Absage-Mail: Beginne klein geschrieben '
                .'(die Anrede „Guten Tag …," steht bereits davor), 1–2 kurze Absätze mit Leerzeile, '
                .'bedanke dich fürs Interesse, begründe kurz und wertschätzend, ohne konkrete '
                .'Schmerzgrenzen zu nennen, und lade ein, die Kollektion im Blick zu behalten. '
                .'KEINE Grußformel.',
        };

        return implode("\n", $lines);
    }

    /**
     * Gemeinsame Kontextzeilen für alle Prompts.
     *
     * @return array<int, string>
     */
    private function contextLines(PriceProposal $proposal): array
    {
        $watch = $proposal->watch;

        $lines = [
            'Kontext eines Preisvorschlags im Uhren-Shop:',
            'Händler: '.((string) tenant('name')),
            'Uhr: '.($watch?->fullName() ?? 'unbekannt'),
            'Angebotspreis im Shop: '.($proposal->asking_price_at_time !== null ? number_format((float) $proposal->asking_price_at_time, 0, ',', '.').' €' : 'auf Anfrage'),
            'Wunschpreis des Kunden: '.number_format((float) $proposal->proposed_price, 0, ',', '.').' €',
            'Kundenname: '.$proposal->name,
        ];

        if (filled($proposal->message)) {
            $lines[] = 'Nachricht des Kunden: "'.$proposal->message.'"';
        }

        return $lines;
    }

    private function buildPrompt(PriceProposal $proposal, string $tone, ?string $keyPoints): string
    {
        $toneInstruction = match ($tone) {
            'thanks' => 'Bedanke dich für das Interesse und stelle eine höfliche Rückfrage (z. B. zur Vorstellung des Kunden oder zu einer Besichtigung).',
            'negotiate' => 'Signalisiere Verhandlungsbereitschaft, ohne bereits einen konkreten neuen Preis zu nennen — lade zu einem Gespräch ein.',
            'firm' => 'Bleibe freundlich, aber klar beim aktuellen Angebotspreis und begründe kurz den Wert der Uhr (Zustand, Lieferumfang, Service).',
            default => 'Lehne den Vorschlag höflich und wertschätzend ab und lade ein, bei anderen Uhren in Kontakt zu bleiben.',
        };

        $lines = $this->contextLines($proposal);

        $lines[] = 'Schreibe die komplette Antwort-Mail auf diesen Preisvorschlag.';
        $lines[] = 'Gewünschter Tenor: '.$toneInstruction;

        if (filled($keyPoints)) {
            $lines[] = 'Diese Punkte des Händlers unbedingt einarbeiten: '.$keyPoints;
        }

        $lines[] = 'Anrede und Grußformel jeweils in einer eigenen Zeile; '
            .'unterschreibe mit "Mit freundlichen Grüßen" und dem Händlernamen.';

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
