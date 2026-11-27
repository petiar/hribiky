<?php

namespace App\Tests\Controller;

use App\Entity\Mushroom;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SitemapRobotsTest extends WebTestCase
{
    public function testSitemapContainsMainPages(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
        $mushroom = (new Mushroom())->setTitle('Chopok')->setName('Tester')->setLatitude(48.94)->setLongitude(19.59)->setPublished(true);
        $em->persist($mushroom);
        $em->flush();

        $client->request('GET', '/sitemap.xml');

        $this->assertResponseIsSuccessful();
        $xml = new \SimpleXMLElement($client->getResponse()->getContent());
        $locs = array_map('strval', iterator_to_array($xml->xpath('//*[local-name()="loc"]'), false));

        foreach (['/', '/blog', '/about', '/leaderboard', '/' . $mushroom->getId()] as $path) {
            $this->assertContains('http://localhost' . $path, $locs, "Sitemap má obsahovať $path");
        }
        foreach ($locs as $loc) {
            $this->assertStringNotContainsString('/hladat', $loc, 'Vyhľadávanie (noindex) do sitemapy nepatrí');
            $this->assertStringNotContainsString('page=', $loc, 'Strany blogu do sitemapy nepatria');
        }

        // Titulná stránka má lastmod podľa hríbikov
        $home = $xml->xpath('//*[local-name()="url"][*[local-name()="loc"]="http://localhost/"]/*[local-name()="lastmod"]');
        $this->assertCount(1, $home);
    }

    public function testRobotsTxtPointsToSitemapOfCurrentDomain(): void
    {
        $client = static::createClient();

        $client->request('GET', '/robots.txt', [], [], ['HTTP_HOST' => 'hribiky.cz', 'HTTPS' => 'on']);

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('text/plain', $client->getResponse()->headers->get('Content-Type'));
        $body = $client->getResponse()->getContent();
        $this->assertStringContainsString('Sitemap: https://hribiky.cz/sitemap.xml', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringNotContainsString('/hladat', $body);
    }
}
