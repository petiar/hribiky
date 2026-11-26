<?php

namespace App\Tests\Controller;

use App\Entity\Mushroom;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verejné stránky sa musia dať otvoriť (najmä po aktualizáciách Symfony a knižníc).
 */
class PublicPagesSmokeTest extends WebTestCase
{
    #[DataProvider('pages')]
    public function testPageLoads(string $url): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        // Hlavná stránka a 404 zobrazujú náhodný hríbik — nech nejaký existuje.
        $em->persist((new Mushroom())->setTitle('Chopok')->setName('Tester')->setLatitude(48.94)->setLongitude(19.59)->setPublished(true));
        $em->flush();

        $client->request('GET', $url);

        $this->assertResponseIsSuccessful("Stránka $url");
    }

    public static function pages(): array
    {
        return [
            'hlavná stránka (mapa)' => ['/'],
            'blog' => ['/blog'],
            'rebríček' => ['/leaderboard'],
            'o projekte' => ['/about'],
            'kontakt' => ['/contact'],
            'súkromie' => ['/privacy'],
            'prihlásenie' => ['/login'],
            'API kľúč' => ['/generate-key'],
            'sitemap' => ['/sitemap.xml'],
            'vyhľadávanie' => ['/hladat?q=chopok'],
        ];
    }

    public function testMushroomDetailLoads(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $mushroom = (new Mushroom())->setTitle('Chopok')->setName('Tester')->setLatitude(48.94)->setLongitude(19.59)->setPublished(true);
        $em->persist($mushroom);
        $em->flush();

        $client->request('GET', '/' . $mushroom->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Chopok');
    }

    public function testUnknownPageReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/neexistuje-xyz');

        $this->assertResponseStatusCodeSame(404);
    }
}
