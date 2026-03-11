<?php

namespace App\Service;

use App\Entity\BlogPost;
use App\Entity\Mushroom;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BlogPostGeneratorService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-6';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {
    }

    public function generateFromMushroom(Mushroom $mushroom): BlogPost
    {
        $prompt = $this->buildPrompt($mushroom);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'timeout' => 600,
            'max_duration' => 660,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => 16000,
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

        return $this->parseResponse($raw);
    }

    private function buildPrompt(Mushroom $mushroom): string
    {
        $parts = [];
        $parts[] = 'Názov lokality: ' . $mushroom->getTitle();

        if ($mushroom->getDescription()) {
            $parts[] = 'Popis: ' . $mushroom->getDescription();
        }

        $parts[] = sprintf(
            'GPS súradnice: %.6f, %.6f',
            $mushroom->getLatitude(),
            $mushroom->getLongitude()
        );

        if ($mushroom->getAltitude()) {
            $parts[] = 'Nadmorská výška: ' . $mushroom->getAltitude() . ' m';
        }

        if ($mushroom->getCountry()) {
            $parts[] = 'Krajina: ' . $mushroom->getCountry();
        }

        $info = implode("\n", $parts);

        return <<<PROMPT
Si skúsený redaktor slovenského blogu o zaujímavých miestach, turistike a cykloturistike. Na základe nasledujúcich informácií o turistickom rozcestníku (tzv. "hríbik") napíš pútavý blogový článok v slovenčine (1200–1800 slov).

Informácie o lokalite:
{$info}

Štruktúra článku (povinná):
1. Úvod – čo robí toto miesto výnimočným, prečo sa sem oplatí ísť
2. Popis lokality – krajina, terén, nadmorská výška, charakter prostredia (les, lúka, vrch, dolina...)
3. Čo v okolí vidieť a zažiť – pamätihodnosti, výhľady, prírodné zaujímavosti, dediny, hrady, vodopády a pod.
4. Turistika a cykloturistika – aké trasy vedú cez toto miesto alebo z neho vychádzajú, ich náročnosť
5. Najlepší čas na návštevu – ročné obdobie, špeciálne udalosti, sezónne atrakcie
6. Ako sa tam dostať – auto, MHD, vlak, parkovanie, praktické tipy
7. Odporúčané vybavenie – tu prirodzene (nie reklame-štýlom) zmieň: pevnú turistickú obuv, batoh, mapu alebo GPS aplikáciu (napr. Mapy.cz, komoot), cyklomapu pre cyklistov, vhodné oblečenie do každého počasia, termosku
8. Záver – motivačné zakončenie, výzva na návštevu

Formátovanie HTML – POVINNÉ, nepoužívaj div tagy:
- Každý odsek textu MUSÍ byť obalený v <p>...</p>
- Nadpis každej sekcie MUSÍ byť obalený v <h2>...</h2>
- Zoznamy MUSIA byť obalené v <ul><li>...</li></ul>
- Názvy miest, vrchov, trás obaľ do <strong>...</strong>
- NIKDY nepoužívaj <div>, <span>, ani žiadne iné tagy

Príklad správneho formátu:
<h2>Popis lokality</h2>
<p>Text odstavca...</p>
<ul><li>Položka 1</li><li>Položka 2</li></ul>

Vráť odpoveď VÝHRADNE ako JSON objekt v tomto formáte (bez akéhokoľvek iného textu):
{
  "title": "Nadpis článku (obsahuj názov lokality a hlavnú aktivitu alebo atrakciu)",
  "shortDescription": "Krátky popis 2-3 vety pre Google snippet",
  "text": "Celý text článku v HTML podľa štruktúry vyššie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}
PROMPT;
    }

    private function parseResponse(string $raw): BlogPost
    {
        // Odstráň ```json ... ``` obal ak existuje
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $cleaned = preg_replace('/\s*```$/', '', trim($cleaned));

        $json = json_decode($cleaned, true);

        // Fallback: vytrhneme prvý JSON objekt z textu
        if (!is_array($json)) {
            preg_match('/\{.*\}/s', $cleaned, $matches);
            $json = isset($matches[0]) ? json_decode($matches[0], true) : [];
        }

        if (!is_array($json) || empty($json)) {
            throw new \RuntimeException(sprintf(
                "Nepodarilo sa parsovať JSON odpoveď z API. Raw odpoveď (prvých 500 znakov):\n%s",
                substr($raw, 0, 500)
            ));
        }

        $post = new BlogPost();
        $post->setTitle($json['title'] ?? 'Lokalita ' . uniqid());
        $post->setShortDescription($json['shortDescription'] ?? '');
        $post->setText($this->fixHtml($json['text'] ?? $raw));
        $post->setTags($json['tags'] ?? []);
        $post->setPublished(false);
        $post->setPublishedAt($this->randomPublishTime());

        return $post;
    }

    private function fixHtml(string $html): string
    {
        // <div class="..."> → zachovaj obsah, zahoď div wrapper
        $html = preg_replace('/<div[^>]*>/i', '', $html);
        $html = str_replace('</div>', '', $html);

        // Prázdne riadky medzi blokmi → <p>
        $html = preg_replace('/\n{2,}/', "\n", trim($html));

        // Holý text (riadok nezačínajúci tagom) → obaľ do <p>
        $html = preg_replace('/^(?!<[hup])/m', '<p>', $html);
        $html = preg_replace('/(?<!\>)$/m', '</p>', $html);

        // Uprac zdvojené <p><p> a </p></p>
        $html = preg_replace('/<p>\s*<p>/i', '<p>', $html);
        $html = preg_replace('/<\/p>\s*<\/p>/i', '</p>', $html);

        // Uprac <p> okolo blokových elementov
        $html = preg_replace('/<p>\s*(<h[2-6]|<ul|<ol)/i', '$1', $html);
        $html = preg_replace('/(</h[2-6]>|<\/ul>|<\/ol>)\s*<\/p>/i', '$1', $html);

        return trim($html);
    }

    private function randomPublishTime(): \DateTimeInterface
    {
        $date = new \DateTime('today');
        $date->setTime(rand(7, 21), rand(0, 59), rand(0, 59));

        return $date;
    }
}