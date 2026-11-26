<?php

namespace App\Tests\Controller;

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
        $hrefs = $crawler->filter('.menu a')->each(fn($a) => strtok((string) $a->attr('href'), '?'));
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
