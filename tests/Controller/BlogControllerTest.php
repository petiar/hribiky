<?php

namespace App\Tests\Controller;

use App\Controller\BlogController;
use App\Entity\BlogPost;
use App\Entity\MushroomArticleLink;
use App\Entity\Mushroom;
use App\Service\ThumbnailService;
use App\Entity\Photo;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class BlogControllerTest extends WebTestCase
{
    private const PER_PAGE = BlogController::POSTS_PER_PAGE;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\Photo p')->execute();
        $this->em->getConnection()->executeStatement('DELETE FROM blog_post_tag');
        $this->em->createQuery('DELETE FROM App\Entity\BlogPost b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Tag t')->execute();
    }

    public function testFirstPageShowsNewestPostsAndLinkToNextPage(): void
    {
        $this->createPosts(self::PER_PAGE + 3);

        $crawler = $this->client->request('GET', '/blog');

        $this->assertResponseIsSuccessful();
        $this->assertSame(self::PER_PAGE, $this->countCards($crawler));
        // Najnovší článok je prvý.
        $this->assertSame('Článok 15', $crawler->filter('.card-title a')->first()->text());
        $this->assertSame('/blog?page=2', $crawler->filter('a[rel="next"]')->attr('href'));
        $this->assertCount(0, $crawler->filter('a[rel="prev"]'));
    }

    public function testSecondPageShowsRemainingPosts(): void
    {
        $this->createPosts(self::PER_PAGE + 3);

        $crawler = $this->client->request('GET', '/blog?page=2');

        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countCards($crawler));
        $this->assertSame('Článok 3', $crawler->filter('.card-title a')->first()->text());
        // Odkaz späť na prvú stránku je bez ?page=1.
        $this->assertSame('/blog', $crawler->filter('a[rel="prev"]')->attr('href'));
        $this->assertCount(0, $crawler->filter('a[rel="next"]'));
    }

    public function testEachPageHasOwnCanonicalAndTitle(): void
    {
        $this->createPosts(self::PER_PAGE + 1);

        $first = $this->client->request('GET', '/blog');
        $this->assertStringEndsWith('/blog', $first->filter('link[rel="canonical"]')->attr('href'));
        $this->assertStringNotContainsString('strana', $first->filter('title')->text());

        $second = $this->client->request('GET', '/blog?page=2');
        $this->assertStringEndsWith('/blog?page=2', $second->filter('link[rel="canonical"]')->attr('href'));
        $this->assertStringContainsString('strana 2', $second->filter('title')->text());
    }

    public function testPageOneRedirectsToBlogIndex(): void
    {
        $this->createPosts(1);

        $this->client->request('GET', '/blog?page=1');

        $this->assertResponseRedirects('/blog', 301);
    }

    #[DataProvider('invalidPageProvider')]
    public function testInvalidPageReturns404(string $page): void
    {
        $this->createPosts(self::PER_PAGE + 1); // 2 stránky

        $this->client->request('GET', '/blog?page=' . $page);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function invalidPageProvider(): array
    {
        return [
            'za koncom' => ['3'],
            'nula' => ['0'],
            'záporná' => ['-1'],
        ];
    }

    public function testNoPaginationWhenPostsFitOnOnePage(): void
    {
        $this->createPosts(self::PER_PAGE);

        $crawler = $this->client->request('GET', '/blog');

        $this->assertResponseIsSuccessful();
        $this->assertSame(self::PER_PAGE, $this->countCards($crawler));
        $this->assertCount(0, $crawler->filter('.pagination'));
    }

    public function testEmptyBlogStillWorks(): void
    {
        $crawler = $this->client->request('GET', '/blog');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Zatiaľ žiadne príspevky', $crawler->text());
    }

    public function testUnpublishedPostsAreNotCounted(): void
    {
        $this->createPosts(self::PER_PAGE);
        $this->createPost('Neschválený', new \DateTime('+1 day'), published: false);

        $crawler = $this->client->request('GET', '/blog');

        // 12 publikovaných sa zmestí na jednu stránku — neschválený nesmie pridať druhú.
        $this->assertCount(0, $crawler->filter('.pagination'));
        $this->assertStringNotContainsString('Neschválený', $crawler->text());
    }

    // --- Náhľady fotiek ---

    public function testCardsUseThumbnailWhenAvailable(): void
    {
        $this->createPostWithPhoto('S nahladom', 'nahlad-test.jpg', withThumb: true);
        $this->createPostWithPhoto('So starym nahladom', 'stary-nahlad-test.jpg', withThumb: 'legacy');
        $this->createPostWithPhoto('Bez nahladu', 'bez-nahladu-test.jpg', withThumb: false);

        $crawler = $this->client->request('GET', '/blog');
        $srcs = $crawler->filter('.card-img-top')->each(fn(Crawler $img) => $img->attr('src'));

        $this->assertContains('/uploads/photos/thumbs-800/nahlad-test.jpg', array_map(fn($s) => strtok($s, '?'), $srcs));
        // Kým sa 800 px náhľad nevygeneruje, použije sa starší 400 px.
        $this->assertContains('/uploads/photos/thumbs/stary-nahlad-test.jpg', array_map(fn($s) => strtok($s, '?'), $srcs));
        // Bez náhľadu sa použije originál (nič sa nerozbije).
        $this->assertContains('/uploads/photos/bez-nahladu-test.jpg', array_map(fn($s) => strtok($s, '?'), $srcs));
    }

    // --- Stránky tagov ---

    public function testTagPagePaginatesOnlyPostsWithThatTag(): void
    {
        $tatry = $this->createTag('Tatry', 'tatry');
        $this->createPosts(self::PER_PAGE + 2, $tatry);
        $this->createPosts(5); // bez tagu — nesmú sa započítať

        $first = $this->client->request('GET', '/blog/tag/tatry');
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::PER_PAGE, $this->countCards($first));
        // Počet v perexe je celkový, nie len na tejto stránke.
        $this->assertStringContainsString('14 článkov s týmto tagom', $first->text());
        $this->assertSame('/blog/tag/tatry?page=2', $first->filter('a[rel="next"]')->attr('href'));

        $second = $this->client->request('GET', '/blog/tag/tatry?page=2');
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countCards($second));
        $this->assertSame('/blog/tag/tatry', $second->filter('a[rel="prev"]')->attr('href'));
    }

    public function testTagPageHasOwnCanonicalAndTitle(): void
    {
        $tatry = $this->createTag('Tatry', 'tatry');
        $this->createPosts(self::PER_PAGE + 1, $tatry);

        $second = $this->client->request('GET', '/blog/tag/tatry?page=2');

        $this->assertStringEndsWith('/blog/tag/tatry?page=2', $second->filter('link[rel="canonical"]')->attr('href'));
        $this->assertStringContainsString('„Tatry“ – strana 2', $second->filter('title')->text());
    }

    public function testTagPageOneRedirectsAndOutOfRangeReturns404(): void
    {
        $tatry = $this->createTag('Tatry', 'tatry');
        $this->createPosts(3, $tatry);

        $this->client->request('GET', '/blog/tag/tatry?page=1');
        $this->assertResponseRedirects('/blog/tag/tatry', 301);

        $this->client->request('GET', '/blog/tag/tatry?page=2');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTagWithoutPostsStillWorks(): void
    {
        $this->createTag('Prázdny', 'prazdny');

        $crawler = $this->client->request('GET', '/blog/tag/prazdny');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('.pagination'));
    }

    // --- Pomocné metódy ---

    /**
     * @param bool|string $withThumb true = nový 800 px náhľad, 'legacy' = len starší 400 px náhľad (thumbs/), false = žiadny
     */
    public function testArticleLinksToItsMushroomAreasAndNeighbours(): void
    {
        $mushroom = (new Mushroom())->setTitle('Kráľova hoľa, vrchol')->setName('T')->setLatitude(46.5)->setLongitude(21.5)
            ->setAltitude(1946)->setPublished(true)
            ->setAddress('Šumiac', 'okres Brezno', 'Banskobystrický kraj')
            ->setMountainRange('Nízke Tatry', 'Nízké Tatry', 0);
        $neighbour = (new Mushroom())->setTitle('Pod Kráľovou hoľou')->setName('T')->setLatitude(46.51)->setLongitude(21.5)->setPublished(true);
        $post = (new BlogPost())->setTitle('Výstup na Kráľovu hoľu')->setSlug('clanok-' . uniqid())->setShortDescription('Popis')
            ->setText('Text')->setPublished(true)->setPublishedAt(new \DateTime('2026-01-01'));
        $link = (new MushroomArticleLink())->setMushroom($mushroom)->setBlogPost($post)->setTitle('Výstup')->setUrl('https://hribiky.sk/blog/x');
        foreach ([$mushroom, $neighbour, $post, $link] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $crawler = $this->client->request('GET', '/blog/' . $post->getSlug());

        $this->assertResponseIsSuccessful();
        $box = $crawler->filter('.blog-mushroom');
        $this->assertStringContainsString('Kráľova hoľa, vrchol', $box->text());
        $this->assertStringContainsString('(1946 m n. m.)', $box->text());
        $this->assertCount(1, $box->filter('a[href="/pohorie/nizke-tatry"]'));
        $this->assertCount(1, $box->filter('a[href="/kraj/banskobystricky-kraj"]'));
        $this->assertCount(1, $box->filter('a[href="/' . $mushroom->getId() . '"].btn'));
        $this->assertCount(1, $crawler->filter('.blog-mushroom-line a[href="/' . $mushroom->getId() . '"]'));
        $this->assertStringContainsString('Pod Kráľovou hoľou', $crawler->filter('#v-okoli')->text());
        $this->assertStringContainsString('"@id": ' . json_encode('http://localhost/' . $mushroom->getId()), $this->client->getResponse()->getContent());
    }

    public function testArticleWithoutMushroomHasNoBox(): void
    {
        $this->createPosts(1);
        $crawler = $this->client->request('GET', '/blog');
        $this->client->click($crawler->filter('.card a')->first()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $this->client->getCrawler()->filter('.blog-mushroom'));
    }

    private function createPostWithPhoto(string $title, string $filename, bool|string $withThumb): void
    {
        $dir = static::getContainer()->getParameter('photos_directory');
        touch($dir . '/' . $filename);
        foreach ([ThumbnailService::THUMB_SUBDIR => $withThumb === true, 'thumbs' => $withThumb === 'legacy'] as $subdir => $create) {
            @mkdir($dir . '/' . $subdir, 0777, true);
            $create ? touch($dir . '/' . $subdir . '/' . $filename) : @unlink($dir . '/' . $subdir . '/' . $filename);
        }

        $post = (new BlogPost())
            ->setTitle($title)
            ->setSlug('clanok-' . uniqid())
            ->setShortDescription('Popis')
            ->setText('Text')
            ->setPublished(true)
            ->setPublishedAt(new \DateTime('2026-01-01'));
        $photo = (new Photo())->setPath($filename)->setOwner(BlogPost::class)->setBlogPost($post);
        $this->em->persist($post);
        $this->em->persist($photo);
        $this->em->flush();
        $this->em->clear();
    }

    /** Vytvorí $count publikovaných článkov; „Článok N“ je tým novší, čím vyššie N. */
    private function createPosts(int $count, ?Tag $tag = null): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->createPost('Článok ' . $i, new \DateTime(sprintf('2026-01-01 +%d days', $i)), tag: $tag);
        }
        $this->em->flush();
    }

    private function createTag(string $name, string $slug): Tag
    {
        $tag = (new Tag())->setName($name)->setSlug($slug);
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    private function createPost(string $title, \DateTime $publishedAt, bool $published = true, ?Tag $tag = null): void
    {
        $post = (new BlogPost())
            ->setTitle($title)
            ->setSlug('clanok-' . uniqid())
            ->setShortDescription('Krátky popis')
            ->setText('<p>Text článku</p>')
            ->setPublished($published)
            ->setPublishedAt($publishedAt);

        if ($tag !== null) {
            $post->setTags([$tag]);
        }

        $this->em->persist($post);
        $this->em->flush();
    }

    private function countCards(Crawler $crawler): int
    {
        return $crawler->filter('.card-title')->count();
    }
}
