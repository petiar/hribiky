<?php

namespace App\Service;

use App\Entity\BlogPost;
use App\Entity\Mushroom;
use App\Repository\TagRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BlogPostGeneratorService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-6';

    /** @var string[] Tag names waiting to be applied after the first flush */
    private array $pendingTagNames = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private TagRepository $tagRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Must be called AFTER the BlogPost has been persisted and flushed (so it has an ID
     * and Doctrine wraps its tags in a PersistentCollection). Doctrine ORM 3 only tracks
     * ManyToMany join-table changes on PersistentCollection, not on the plain ArrayCollection
     * that a freshly constructed entity carries.
     */
    public function applyPendingTags(BlogPost $post): void
    {
        foreach ($this->pendingTagNames as $tagName) {
            $post->addTag($this->tagRepository->findOrCreate($tagName));
        }
        $this->pendingTagNames = [];
    }

    public function generateDevPost(Mushroom $mushroom): BlogPost
    {
        $post = new BlogPost();
        $post->setTitle('[DEV] ' . $mushroom->getTitle());
        $post->setShortDescription('Toto je testovací článok vygenerovaný bez AI pre lokalitu ' . $mushroom->getTitle() . '.');
        $post->setText(<<<HTML
<h2>Lorem ipsum</h2>
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
<h2>Popis lokality</h2>
<p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
<h2>Turistika a cykloturistika</h2>
<p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>
<h2>Záver</h2>
<p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
HTML);
        $this->pendingTagNames = ['dev', 'test', 'lorem'];
        $post->setPublished(false);
        $post->setPublishedAt($this->randomPublishTime());

        return $post;
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
                'max_tokens' => 8192,
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

        $this->logger->info('BlogPostGenerator: raw AI response (first 1000 chars)', [
            'raw' => substr($raw, 0, 1000),
        ]);

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
Si skúsený redaktor slovenského blogu o zaujímavých miestach, turistike a cykloturistike. Na základe nasledujúcich informácií o turistickom rozcestníku (tzv. "hríbik") napíš pútavý blogový článok v slovenčine (600–900 slov).

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

Ak sú v texte spomínané nejaké weby, uveď ich ako odkazy na tie weby, nech sa na ne dá kliknúť.
Formát odpovede – POVINNÝ XML (bez akéhokoľvek iného textu pred ani za):

<article>
  <title>Nadpis článku (obsahuj názov lokality a hlavnú aktivitu alebo atrakciu)</title>
  <description>Krátky popis 2-3 vety pre Google snippet</description>
  <tags>tag1,tag2,tag3,tag4,tag5</tags>
  <text><![CDATA[Celý text článku v HTML]]></text>
</article>

Pravidlá pre HTML v tagu text:
- Každý odsek textu obaľ do <p>...</p>
- Nadpis každej sekcie obaľ do <h2>...</h2>
- Zoznamy obaľ do <ul><li>...</li></ul>
- Názvy miest, vrchov, trás obaľ do <strong>...</strong>
- Nepoužívaj <div>, <span>, ani iné tagy
PROMPT;
    }

    private function parseResponse(string $raw): BlogPost
    {
        // Vytrhneme <article>...</article> blok
        if (!preg_match('/<article>.*<\/article>/s', $raw, $matches)) {
            throw new \RuntimeException(sprintf(
                "Odpoveď neobsahuje <article> blok. Raw odpoveď (prvých 500 znakov):\n%s",
                substr($raw, 0, 500)
            ));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($matches[0]);

        if ($xml === false) {
            throw new \RuntimeException(sprintf(
                "Nepodarilo sa parsovať XML. Raw odpoveď (prvých 500 znakov):\n%s",
                substr($raw, 0, 500)
            ));
        }

        $tagNames = array_values(array_filter(array_map('trim', explode(',', (string) $xml->tags))));

        $this->logger->info('BlogPostGenerator: parsed tags', [
            'raw_tags' => (string) $xml->tags,
            'tag_names' => $tagNames,
        ]);

        // Store for applyPendingTags() — tags must be added AFTER the first flush
        // because Doctrine 3 only inserts ManyToMany join-table rows for PersistentCollection.
        $this->pendingTagNames = $tagNames;

        $post = new BlogPost();
        $post->setTitle(mb_substr((string) $xml->title, 0, 255) ?: 'Lokalita ' . uniqid());
        $post->setShortDescription((string) $xml->description);
        $post->setText($this->fixHtml((string) $xml->text));
        $post->setPublished(false);
        $post->setPublishedAt($this->randomPublishTime());

        return $post;
    }

    private function fixHtml(string $html): string
    {
        $html = preg_replace('/<div[^>]*>/i', '', $html);
        $html = str_replace('</div>', '', $html);

        $html = preg_replace('/\n{2,}/', "\n", trim($html));

        $html = preg_replace('/^(?!<[hup])/m', '<p>', $html);
        $html = preg_replace('/(?<!\>)$/m', '</p>', $html);

        $html = preg_replace('/<p>\s*<p>/i', '<p>', $html);
        $html = preg_replace('/<\/p>\s*<\/p>/i', '</p>', $html);

        $html = preg_replace('/<p>\s*(<h[2-6]|<ul|<ol)/i', '$1', $html);
        $html = preg_replace('~(<\/h[2-6]>|<\/ul>|<\/ol>)\s*<\/p>~i', '$1', $html);

        return trim($html);
    }

    private function randomPublishTime(): \DateTimeInterface
    {
        $date = new \DateTime('today');
        $date->setTime(rand(7, 21), rand(0, 59), rand(0, 59));

        return $date;
    }
}
