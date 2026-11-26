<?php

namespace App\Tests\Controller;

use App\Controller\SearchController;
use App\Entity\BlogPost;
use App\Entity\Mushroom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Pozn.: testy bežia nad SQLite, ktoré (na rozdiel od MySQL utf8mb4_unicode_ci)
 * neignoruje diakritiku — preto sa v dotazoch používajú slová bez diakritiky.
 */
class SearchControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\UserBadge b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Photo p')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomComment c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
        $this->em->getConnection()->executeStatement('DELETE FROM blog_post_tag');
        $this->em->createQuery('DELETE FROM App\Entity\BlogPost b')->execute();
    }

    public function testFindsMushroomByTitleAndDescription(): void
    {
        $this->createMushroom('Chata Chopok', 'Rozcestnik pri chate.');
        $this->createMushroom('Sedlo Javorie', 'Vyhlad na Chopok a Dumbier.');
        $this->createMushroom('Krivan', 'Nic spolocne.');

        $crawler = $this->search('chopok');

        // Zhoda v názve je prvá, potom zhoda v popise.
        $this->assertSame(['Chata Chopok', 'Sedlo Javorie'], $this->titles($crawler, 'vysledky-hribiky'));
    }

    public function testFindsBlogPostByTitleShortDescriptionAndText(): void
    {
        $this->createPost('Vylet na Rysy', 'Kratky popis', 'Text');
        $this->createPost('Iny clanok', 'Cestou na Rysy sme...', 'Text');
        $this->createPost('Treti clanok', 'Popis', '<p>Pokracovali sme na <strong>Rysy</strong>.</p>');
        $this->createPost('Nesuvisiaci', 'Popis', 'Text');

        $crawler = $this->search('rysy');

        $this->assertCount(3, $this->titles($crawler, 'vysledky-blog'));
        $this->assertNotContains('Nesuvisiaci', $this->titles($crawler, 'vysledky-blog'));
    }

    public function testTitleMatchIsRankedFirst(): void
    {
        // Novší hríbik s „Chopok“ len v popise by bez rankingu bol prvý.
        $this->createMushroom('Stara chata', 'Pod vrcholom Chopok.', new \DateTime('2026-06-01'));
        $this->createMushroom('Chopok vrchol', 'Popis.', new \DateTime('2025-01-01'));

        $crawler = $this->search('chopok');

        $this->assertSame('Chopok vrchol', $this->titles($crawler, 'vysledky-hribiky')[0]);
    }

    public function testAllWordsMustMatch(): void
    {
        $this->createMushroom('Liptovsky Hradok, Bela', 'Popis.');
        $this->createMushroom('Liptovsky Mikulas', 'Popis.');

        $crawler = $this->search('liptovsky bela');

        $this->assertSame(['Liptovsky Hradok, Bela'], $this->titles($crawler, 'vysledky-hribiky'));
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $this->createMushroom('Chopok', 'Popis.');

        $this->assertSame(['Chopok'], $this->titles($this->search('CHOPOK'), 'vysledky-hribiky'));
    }

    public function testUnpublishedAreNotFound(): void
    {
        $this->createMushroom('Chopok schvaleny', 'Popis.');
        $this->createMushroom('Chopok neschvaleny', 'Popis.', published: false);
        $this->createPost('Chopok clanok neschvaleny', 'Popis', 'Text', published: false);

        $crawler = $this->search('chopok');

        $this->assertSame(['Chopok schvaleny'], $this->titles($crawler, 'vysledky-hribiky'));
        $this->assertSame([], $this->titles($crawler, 'vysledky-blog'));
    }

    public function testLikeWildcardsAreSearchedLiterally(): void
    {
        $this->createMushroom('Chata', 'Popis.');
        $this->createMushroom('Zlava 50% na chate', 'Popis.');

        // „%“ a „_“ nesmú fungovať ako zástupné znaky (inak by „%%“ našlo všetko).
        $this->assertSame([], $this->titles($this->search('%%'), 'vysledky-hribiky'));
        $this->assertSame([], $this->titles($this->search('__'), 'vysledky-hribiky'));
        $this->assertSame(['Zlava 50% na chate'], $this->titles($this->search('50%'), 'vysledky-hribiky'));
    }

    public function testTooShortQueryShowsHint(): void
    {
        $this->createMushroom('A', 'Popis.');

        $crawler = $this->search('a');

        $this->assertStringContainsString('Zadaj aspoň 2 znaky', $crawler->text());
        $this->assertCount(0, $crawler->filter('.search-result'));
    }

    public function testNoResultsMessage(): void
    {
        $crawler = $this->search('nezmyselxyz');

        $this->assertStringContainsString('Pre „nezmyselxyz“ sme nič nenašli', $crawler->text());
    }

    public function testEmptyQueryShowsOnlyForm(): void
    {
        $crawler = $this->client->request('GET', '/hladat');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#searchPageForm'));
        $this->assertCount(0, $crawler->filter('.search-result'));
    }

    public function testResultsPageIsNotIndexed(): void
    {
        $crawler = $this->search('chopok');

        $this->assertSame('noindex,follow', $crawler->filter('meta[name="robots"]')->attr('content'));
    }

    public function testNavbarContainsSearchFormWithCurrentQuery(): void
    {
        $crawler = $this->client->request('GET', '/blog');
        $this->assertSame('/hladat', $crawler->filter('#navbarSearch')->attr('action'));
        $this->assertSame('', $crawler->filter('#navbarSearchInput')->attr('value'));

        $crawler = $this->search('chopok');
        $this->assertSame('chopok', $crawler->filter('#navbarSearchInput')->attr('value'));
    }

    public function testQueryIsEscapedInOutput(): void
    {
        $crawler = $this->search('<script>alert(1)</script>');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('.container script'));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->client->getResponse()->getContent());
    }

    public function testExactAndPrefixTitleMatchesRankFirst(): void
    {
        // Všetky obsahujú „bela“ v názve; novšie by bez rankingu boli vyššie.
        $this->createMushroom('Liptovsky Hradok, Bela', 'Popis.', new \DateTime('2026-06-01'));
        $this->createMushroom('Bela pri moste', 'Popis.', new \DateTime('2026-03-01'));
        $this->createMushroom('Bela', 'Popis.', new \DateTime('2025-01-01'));
        $this->createMushroom('Lavka', 'Pri rieke Bela.', new \DateTime('2026-12-01'));

        $titles = $this->titles($this->search('Bela'), 'vysledky-hribiky');

        $this->assertSame(['Bela', 'Bela pri moste', 'Liptovsky Hradok, Bela', 'Lavka'], $titles);
    }

    // --- Autocomplete (/hladat/navrhy) ---

    public function testSuggestReturnsMushroomsThenPostsWithUrls(): void
    {
        $this->createMushroom('Chopok', 'Popis.', altitude: 2024);
        $this->createPost('Vylet na Chopok', 'Popis', 'Text', slug: 'vylet-na-chopok');

        $data = $this->suggest('chopok');

        $this->assertSame('chopok', $data['query']);
        $this->assertSame('/hladat?q=chopok', $data['searchUrl']);
        $this->assertCount(2, $data['items']);

        $this->assertSame('mushroom', $data['items'][0]['type']);
        $this->assertSame('Chopok', $data['items'][0]['title']);
        $this->assertSame('2024 m n. m.', $data['items'][0]['subtitle']);
        $this->assertMatchesRegularExpression('#^/\d+$#', $data['items'][0]['url']);

        $this->assertSame('post', $data['items'][1]['type']);
        $this->assertSame('/blog/vylet-na-chopok', $data['items'][1]['url']);
    }

    public function testSuggestIsLimited(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->createMushroom('Chata ' . $i, 'Popis.');
            $this->createPost('Chata clanok ' . $i, 'Popis', 'Text');
        }

        $types = array_count_values(array_column($this->suggest('chata')['items'], 'type'));

        $this->assertSame(SearchController::SUGGEST_MUSHROOMS, $types['mushroom']);
        $this->assertSame(SearchController::SUGGEST_POSTS, $types['post']);
    }

    public function testSuggestShortQueryReturnsNoItems(): void
    {
        $this->createMushroom('Chopok', 'Popis.');

        $this->assertSame([], $this->suggest('c')['items']);
        $this->assertSame([], $this->suggest('')['items']);
    }

    public function testSuggestExcludesUnpublishedAndReturnsOnlyPublicFields(): void
    {
        $this->createMushroom('Chopok schvaleny', 'Popis.');
        $this->createMushroom('Chopok neschvaleny', 'Popis.', published: false);

        $data = $this->suggest('chopok');

        $this->assertSame(['Chopok schvaleny'], array_column($data['items'], 'title'));
        $this->assertEqualsCanonicalizing(['type', 'title', 'subtitle', 'url'], array_keys($data['items'][0]));
        $this->assertStringNotContainsString('@', $this->client->getResponse()->getContent());
    }

    public function testSuggestIsCachedBriefly(): void
    {
        $this->suggest('chopok');

        $cacheControl = $this->client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
    }

    public function testNavbarHasAutocompleteWiring(): void
    {
        $crawler = $this->client->request('GET', '/blog');

        $this->assertSame('/hladat/navrhy', $crawler->filter('#navbarSearch')->attr('data-suggest-url'));
        $this->assertSame('combobox', $crawler->filter('#navbarSearchInput')->attr('role'));
        $this->assertSame('navbarSearchSuggestions', $crawler->filter('#navbarSearchInput')->attr('aria-controls'));
        $this->assertCount(1, $crawler->filter('#navbarSearchSuggestions[role="listbox"]'));
        $this->assertCount(1, $crawler->filter('script[src*="js/search-autocomplete.js"]'));
    }

    // --- Pomocné metódy ---

    private function suggest(string $query): array
    {
        $this->client->request('GET', '/hladat/navrhy', ['q' => $query]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function search(string $query): Crawler
    {
        $crawler = $this->client->request('GET', '/hladat', ['q' => $query]);
        $this->assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * @return string[] názvy výsledkov v sekcii (#vysledky-hribiky / #vysledky-blog)
     */
    private function titles(Crawler $crawler, string $sectionId): array
    {
        return $crawler->filter('#' . $sectionId . ' .search-result .fw-semibold')->each(fn(Crawler $n) => $n->text());
    }

    private function createMushroom(string $title, ?string $description, \DateTime $createdAt = new \DateTime('2026-01-01'), bool $published = true, ?int $altitude = null): void
    {
        $mushroom = (new Mushroom())
            ->setTitle($title)
            ->setEmail('autor@example.com')
            ->setAltitude($altitude)
            ->setName('Tester')
            ->setDescription($description)
            ->setLatitude(48.1486)
            ->setLongitude(17.1077)
            ->setPublished($published)
            ->setCreatedAt($createdAt);

        $this->em->persist($mushroom);
        $this->em->flush();
    }

    private function createPost(string $title, string $shortDescription, string $text, bool $published = true, ?string $slug = null): void
    {
        $post = (new BlogPost())
            ->setTitle($title)
            ->setSlug($slug ?? 'clanok-' . uniqid())
            ->setShortDescription($shortDescription)
            ->setText($text)
            ->setPublished($published)
            ->setPublishedAt(new \DateTime('2026-01-01'));

        $this->em->persist($post);
        $this->em->flush();
    }
}
