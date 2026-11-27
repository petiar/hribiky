<?php

namespace App\Tests\Controller;

use App\Entity\Mushroom;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Stránky pohorí a krajov. Testovacia DB sa medzi testami nečistí — preto vlastné (vymyslené) názvy oblastí.
 */
class AreaControllerTest extends WebTestCase
{
    private function mushroom(string $title, string $range, string $region, ?int $altitude = null, bool $published = true, int $near = 0): Mushroom
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $m = (new Mushroom())->setTitle($title)->setName('T')->setLatitude(48.9)->setLongitude(19.5)
            ->setAltitude($altitude)->setPublished($published)
            ->setAddress('Obec ' . $title, 'okres Test', $region)
            ->setMountainRange($range, $range . ' CZ', $near);
        $em->persist($m);
        $em->flush();

        return $m;
    }

    public function testRangePageListsPublishedMushroomsFromHighest(): void
    {
        $client = static::createClient();
        $this->mushroom('Nízky', 'Skúšobné vrchy', 'Skúšobný kraj', 800);
        $this->mushroom('Vysoký', 'Skúšobné vrchy', 'Skúšobný kraj', 1500);
        $this->mushroom('Na úpätí', 'Skúšobné vrchy', 'Iný skúšobný kraj', null, near: 300);
        $this->mushroom('Neschválený', 'Skúšobné vrchy', 'Skúšobný kraj', 2000, published: false);
        $this->mushroom('Inde', 'Iné skúšobné vrchy', 'Skúšobný kraj');

        $crawler = $client->request('GET', '/pohorie/skusobne-vrchy');

        $this->assertResponseIsSuccessful();
        $this->assertSame('Skúšobné vrchy – turistické rozcestníky (3) | Hríbiky.sk', $crawler->filter('title')->text());
        $this->assertSame('Hríbiky v pohorí Skúšobné vrchy', $crawler->filter('h1')->text());
        $titles = $crawler->filter('.area-card h2')->each(fn ($n) => $n->text());
        $this->assertSame(['Vysoký', 'Nízky', 'Na úpätí'], $titles);
        $this->assertStringContainsString('Najvyššie položený je Vysoký (1500 m n. m.)', $crawler->filter('.lead')->text());
        $this->assertStringContainsString('na úpätí', $crawler->filter('.area-card')->last()->text());
        $this->assertCount(0, $crawler->filter('meta[name="robots"]'));

        // Súvisiace kraje s počtom hríbikov.
        $this->assertSame('/kraj/skusobny-kraj', $crawler->filter('a.badge')->first()->attr('href'));

        // Mapa dostane len hríbiky oblasti.
        preg_match('/const hribiky = (.*);/', $client->getResponse()->getContent(), $match);
        $this->assertSame(['Vysoký', 'Nízky', 'Na úpätí'], array_column(json_decode($match[1], true, flags: JSON_THROW_ON_ERROR), 'title'));
    }

    public function testRegionPageAndCzechNames(): void
    {
        $client = static::createClient(server: ['HTTP_HOST' => 'hribiky.cz']);
        $this->mushroom('Kopec', 'Moravské skúšobné hory', 'Testovací kraj');
        $this->mushroom('Dolina', 'Moravské skúšobné hory', 'Testovací kraj');

        $crawler = $client->request('GET', '/kraj/testovaci-kraj');

        $this->assertResponseIsSuccessful();
        $this->assertSame('Hříbky – Testovací kraj', $crawler->filter('h1')->text());
        $this->assertStringContainsString('Máme tu 2 hříbky.', $crawler->filter('.lead')->text());
        $this->assertStringContainsString('Moravské skúšobné hory CZ 2', $crawler->filter('a.badge')->text());
    }

    public function testSingleMushroomPageIsNoindexAndNotInSitemap(): void
    {
        $client = static::createClient();
        $this->mushroom('Samotár', 'Osamelé skúšobné vrchy', 'Osamelý skúšobný kraj');

        $crawler = $client->request('GET', '/pohorie/osamele-skusobne-vrchy');
        $this->assertResponseIsSuccessful();
        $this->assertSame('noindex,follow', $crawler->filter('meta[name="robots"]')->attr('content'));
        $this->assertStringContainsString('Zatiaľ tu máme jeden hríbik.', $crawler->filter('.lead')->text());

        $this->mushroom('Prvý', 'Dvojité skúšobné vrchy', 'Osamelý skúšobný kraj');
        $this->mushroom('Druhý', 'Dvojité skúšobné vrchy', 'Osamelý skúšobný kraj');
        $client->request('GET', '/sitemap.xml');
        $sitemap = $client->getResponse()->getContent();
        $this->assertStringContainsString('/pohorie/dvojite-skusobne-vrchy</loc>', $sitemap);
        $this->assertStringNotContainsString('/pohorie/osamele-skusobne-vrchy', $sitemap);
        $this->assertStringContainsString('/kraj/osamely-skusobny-kraj</loc>', $sitemap);
        $this->assertStringContainsString('/pohoria-a-kraje</loc>', $sitemap);
    }

    public function testIndexListsRangesAndRegions(): void
    {
        $client = static::createClient();
        $this->mushroom('A', 'Indexové vrchy', 'Indexový kraj');

        $crawler = $client->request('GET', '/pohoria-a-kraje');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href="/pohorie/indexove-vrchy"]'));
        $this->assertCount(1, $crawler->filter('a[href="/kraj/indexovy-kraj"]'));
    }

    public function testUnknownAreaIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pohorie/neexistujuce-pohorie');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDetailLinksToAreaPages(): void
    {
        $client = static::createClient();
        $m = $this->mushroom('Odkazový', 'Odkazové vrchy', 'Odkazový kraj');

        $crawler = $client->request('GET', '/' . $m->getId());

        $this->assertCount(1, $crawler->filter('.md-info a[href="/pohorie/odkazove-vrchy"]'));
        $this->assertCount(1, $crawler->filter('.md-info a[href="/kraj/odkazovy-kraj"]'));
    }
}
