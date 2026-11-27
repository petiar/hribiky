<?php

namespace App\Tests\Controller;

use App\Entity\Mushroom;
use App\Twig\SeoExtension;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Titulky, nadpisy, meta popisy a štruktúrované dáta (JSON-LD) verejných stránok.
 */
class SeoTest extends WebTestCase
{
    private function createMushroom(?string $description, ?int $altitude = 2024, ?string $country = 'SK'): Mushroom
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $mushroom = (new Mushroom())->setTitle('Chopok')->setName('Tester')->setLatitude(48.94)->setLongitude(19.59)
            ->setAltitude($altitude)->setCountry($country)->setDescription($description)->setPublished(true);
        $em->persist($mushroom);
        $em->flush();

        return $mushroom;
    }

    private function metaDescription($crawler): string
    {
        return $crawler->filter('meta[name="description"]')->attr('content');
    }

    public function testDetailHasTitleWithSiteNameAndH1(): void
    {
        $client = static::createClient();
        $mushroom = $this->createMushroom(null);

        $crawler = $client->request('GET', '/' . $mushroom->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSame('Chopok (2024 m n. m.) – turistický rozcestník | Hríbiky.sk', $crawler->filter('title')->text());
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertSame('Chopok (2024 m n. m.)', $crawler->filter('h1')->text());
    }

    public function testDetailShortDescriptionIsComposedFromData(): void
    {
        $client = static::createClient();
        $mushroom = $this->createMushroom('Krátky popis.');

        $crawler = $client->request('GET', '/' . $mushroom->getId());

        $this->assertSame(
            'Hríbik Chopok, 2024 m n. m., Slovensko – turistický rozcestník s fotkami, GPS súradnicami a polohou na mape.',
            $this->metaDescription($crawler),
        );
    }

    public function testDetailWithoutAltitudeAndCountry(): void
    {
        $client = static::createClient();
        $mushroom = $this->createMushroom(null, null, null);

        $crawler = $client->request('GET', '/' . $mushroom->getId());

        $this->assertSame('Chopok – turistický rozcestník | Hríbiky.sk', $crawler->filter('title')->text());
        $this->assertSame(
            'Hríbik Chopok – turistický rozcestník s fotkami, GPS súradnicami a polohou na mape.',
            $this->metaDescription($crawler),
        );
    }

    public function testDetailLongDescriptionIsUsedAndTruncated(): void
    {
        $client = static::createClient();
        $long = str_repeat("Krásny výhľad na Ďumbier\na hrebeň Nízkych Tatier. ", 10);
        $mushroom = $this->createMushroom($long);

        $crawler = $client->request('GET', '/' . $mushroom->getId());
        $description = $this->metaDescription($crawler);

        $this->assertStringStartsWith('Krásny výhľad na Ďumbier a hrebeň', $description);
        $this->assertStringEndsWith('…', $description);
        $this->assertLessThanOrEqual(155, mb_strlen($description));
        $this->assertStringNotContainsString("\n", $description);
    }

    public function testDetailInCzech(): void
    {
        $client = static::createClient(server: ['HTTP_HOST' => 'hribiky.cz']);
        $mushroom = $this->createMushroom(null, 1100, 'CZ');

        $crawler = $client->request('GET', '/' . $mushroom->getId());

        $this->assertSame('Chopok (1100 m n. m.) – turistický rozcestník | Hříbky.cz', $crawler->filter('title')->text());
        $this->assertSame(
            'Hříbek Chopok, 1100 m n. m., Česko – turistický rozcestník s fotkami, GPS souřadnicemi a polohou na mapě.',
            $this->metaDescription($crawler),
        );
    }

    public function testDetailJsonLdCannotBreakOutOfScript(): void
    {
        $client = static::createClient();
        $mushroom = $this->createMushroom('Popis </script><script>alert(1)</script> s dostatočne dlhým textom, aby sa použil ako meta popis.');

        $client->request('GET', '/' . $mushroom->getId());
        $html = $client->getResponse()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }

    public function testHomeHasTitleH1DescriptionAndJsonLd(): void
    {
        $client = static::createClient();
        $this->createMushroom(null);

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSame('Hríbiky.sk – mapa turistických rozcestníkov na Slovensku a v Česku', $crawler->filter('title')->text());
        $this->assertCount(1, $crawler->filter('h1.visually-hidden'));
        $this->assertMatchesRegularExpression('/^Mapa \d+ turistických rozcestníkov/', $this->metaDescription($crawler));

        $graph = null;
        foreach ($crawler->filter('script[type="application/ld+json"]') as $script) {
            $data = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
            $graph = $data['@graph'] ?? $graph;
        }
        $this->assertNotNull($graph, 'Hlavná stránka nemá JSON-LD @graph');
        $types = array_column($graph, '@type');
        $this->assertContains('WebSite', $types);
        $this->assertContains('Organization', $types);

        $website = $graph[array_search('WebSite', $types, true)];
        $this->assertStringEndsWith('/hladat?q={search_term_string}', $website['potentialAction']['target']['urlTemplate']);
    }

    public function testMetaTextFilter(): void
    {
        $seo = new SeoExtension();

        $this->assertSame('a b', $seo->metaText("  <b>a</b>\n\n b "));
        $this->assertSame('', $seo->metaText(null));
        $this->assertSame('jeden dva…', $seo->metaText('jeden dva tri', 11));
    }
}
