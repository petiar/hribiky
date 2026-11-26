<?php

namespace App\Tests\Controller;

use App\Entity\AccessLog;
use App\Entity\Blacklist;
use App\Entity\BlogPost;
use App\Entity\Mushroom;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Administrácia (EasyAdmin) sa po aktualizáciách musí dať otvoriť.
 * Stránky sa berú z routera — test pokryje aj sekcie pridané neskôr.
 */
class AdminSmokeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\UserBadge b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();

        $admin = (new User())->setEmail('admin@test.sk')->setPassword('x')->setRoles(['ROLE_ADMIN']);
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }

    public function testAnonymousIsNotLetIn(): void
    {
        $this->client->restart();
        $this->client->request('GET', '/admin');

        $this->assertResponseRedirects();
        $this->assertStringContainsString('login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testDashboardLoadsWithMenuLinksToAllSections(): void
    {
        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        // EasyAdmin 5: .ea-sidebar-item-link (EasyAdmin 4: .menu a)
        $hrefs = $crawler->filter('.ea-sidebar-item-link, .menu a')->each(fn($a) => strtok((string) $a->attr('href'), '?'));
        foreach (['/admin/rozcestnik', '/admin/rozcestnik-update', '/admin/blog-post', '/admin/tag', '/admin/mushroom-article-link',
                  '/admin/feedback', '/admin/access-log', '/admin/blacklist', '/admin/user'] as $section) {
            $this->assertContains($section, $hrefs, "V menu chýba odkaz na $section");
        }
    }

    public function testEveryCrudIndexAndNewPageLoads(): void
    {
        $urls = $this->adminUrls('/^admin_.+_(index|new)$/');
        $this->assertGreaterThanOrEqual(10, count($urls), 'Očakávané aspoň sekcie index + new.');

        $failed = [];
        foreach ($urls as $name => $url) {
            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            // „new“ môže byť pri niektorých sekciách vypnuté (presmerovanie/403) — nesmie však spadnúť (500).
            $ok = str_ends_with($name, '_new') ? $status < 500 : $status === 200;
            if (!$ok) {
                $failed[] = "$url → $status";
            }
        }

        $this->assertSame([], $failed, "Admin stránky s chybou:\n" . implode("\n", $failed));
    }

    public function testEditPagesForMushroomAndBlogPostLoad(): void
    {
        $mushroom = (new Mushroom())->setTitle('Test')->setName('Tester')->setLatitude(48.1)->setLongitude(17.1);
        $post = (new BlogPost())->setTitle('Test')->setSlug('test-' . uniqid())->setShortDescription('P')->setText('<p>T</p>');
        $this->em->persist($mushroom);
        $this->em->persist($post);
        $this->em->flush();

        $router = static::getContainer()->get('router');
        foreach ([
            $router->generate('admin_rozcestnik_edit', ['entityId' => $mushroom->getId()]),
            $router->generate('admin_blog_post_edit', ['entityId' => $post->getId()]),
        ] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful("Stránka $url");
        }
    }

    /**
     * Vlastné akcie: v EasyAdmin 5 vyžadujú #[AdminRoute], bez neho celá sekcia padala s 500.
     */
    public function testBlockIpActionFromAccessLog(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Blacklist b')->execute();
        // Iné testy vytvárajú záznamy automaticky — nech je testovací na prvej strane zoznamu.
        $this->em->createQuery('DELETE FROM App\Entity\AccessLog l')->execute();
        $log = new AccessLog('203.0.113.7', Mushroom::class, 1, '/1', 'UA');
        $this->em->persist($log);
        $this->em->flush();

        // Odkaz na akciu je v zozname
        $crawler = $this->client->request('GET', '/admin/access-log');
        $this->assertResponseIsSuccessful();
        $url = '/admin/access-log/' . $log->getId() . '/block-ip';
        $this->assertCount(1, $crawler->filter('a[href^="' . $url . '"]'), 'V zozname chýba akcia „Zablokovať IP“.');

        // Potvrdzovacia stránka a zablokovanie
        $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('203.0.113.7', $this->client->getResponse()->getContent());

        $this->client->request('POST', $url);
        $this->assertResponseRedirects();
        $this->assertNotNull($this->em->getRepository(Blacklist::class)->findOneBy(['ipAddress' => '203.0.113.7']));
    }

    public function testGenerateBlogActionIsLinkedFromMushroomList(): void
    {
        // Samotné generovanie volá AI — tu len overíme, že akcia má route a odkaz v zozname.
        $mushroom = (new Mushroom())->setTitle('Test')->setName('Tester')->setLatitude(48.1)->setLongitude(17.1);
        $this->em->persist($mushroom);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/rozcestnik');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href^="/admin/rozcestnik/' . $mushroom->getId() . '/generate-blog"]'), 'V zozname chýba akcia „Generovať blog“.');
    }

    /**
     * @return array<string, string> názov route → URL
     */
    private function adminUrls(string $pattern): array
    {
        $urls = [];
        foreach (static::getContainer()->get('router')->getRouteCollection() as $name => $route) {
            if (preg_match($pattern, $name) && !str_contains($route->getPath(), '{')) {
                $urls[$name] = $route->getPath();
            }
        }
        ksort($urls);

        return $urls;
    }
}
