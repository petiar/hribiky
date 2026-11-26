<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MushroomDescriptionScorerService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-6';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Bez API volania — jednoduchá heuristika podľa dĺžky textu, pre --dev (šetrí tokeny).
     */
    public function scoreDevDescription(string $description): int
    {
        $length = mb_strlen(trim($description));

        return max(1, min(10, intdiv($length, 60) + 1));
    }

    public function scoreDescription(string $description): int
    {
        $prompt = $this->buildPrompt($description);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'timeout' => 120,
            'max_duration' => 180,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => 256,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ],
        ]);

        $data = $response->toArray();
        $raw = $data['content'][0]['text'] ?? '';

        $this->logger->info('MushroomDescriptionScorer: raw AI response', ['raw' => $raw]);

        return $this->parseResponse($raw);
    }

    private function buildPrompt(string $description): string
    {
        return <<<PROMPT
Si skúsený redaktor slovenského turistického blogu. Ohodnoť kvalitu nasledujúceho popisu turistického rozcestníka ("hríbika") na škále 1–10, kde 10 je pútavý, konkrétny a informatívny popis (spomína okolie, terén, zaujímavosti) a 1 je prázdny alebo bezobsažný popis (napr. jedno slovo, nič nehovoriaca veta).

Popis:
{$description}

Formát odpovede – POVINNÝ, bez akéhokoľvek iného textu pred ani za:
<score>N</score>
PROMPT;
    }

    private function parseResponse(string $raw): int
    {
        if (!preg_match('/<score>\s*(\d{1,2})\s*<\/score>/s', $raw, $matches)) {
            throw new \RuntimeException(sprintf(
                "Odpoveď neobsahuje <score> blok. Raw odpoveď (prvých 500 znakov):\n%s",
                substr($raw, 0, 500)
            ));
        }

        return max(1, min(10, (int) $matches[1]));
    }
}
