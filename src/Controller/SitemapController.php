<?php

// src/Controller/SitemapController.php
namespace App\Controller;

use App\Repository\BlogPostRepository;
use App\Repository\MushroomRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private MushroomRepository $mushroomRepo,
        private BlogPostRepository $blogPostRepo,
        private TagRepository $tagRepo,
    ) {}

    #[Route('sitemap.xml', name: 'sitemap')]
    public function __invoke(): Response
    {
        // 1) základný element sitemap
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $urlset = $xml->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->appendChild($urlset);

        $mushrooms = $this->mushroomRepo->findAllPublished();
        $posts = $this->blogPostRepo->findAllPublished(); // od najnovšieho

        // Titulná stránka (mapa) — zmení sa s každým novým alebo upraveným hríbikom.
        $mapLastmod = null;
        foreach ($mushrooms as $mushroom) {
            $modified = $mushroom->getLastModified();
            if ($mapLastmod === null || $modified > $mapLastmod) {
                $mapLastmod = $modified;
            }
        }
        $this->addUrl($xml, $urlset, 'app_home', [], $mapLastmod, 'daily', '1.0');

        // Blog index — lastmod = najnovší článok.
        $newestPost = $posts[0] ?? null;
        $this->addUrl($xml, $urlset, 'blog_index', [], $newestPost?->getPublishedAt() ?? $newestPost?->getCreatedAt(), 'daily', '0.8');

        // Ďalšie verejné stránky. (Vyhľadávanie má noindex, strany blogu ?page=N netreba — články sú tu všetky.)
        $this->addUrl($xml, $urlset, 'about', [], null, 'monthly', '0.5');
        $this->addUrl($xml, $urlset, 'app_leaderboard', [], null, 'weekly', '0.4');

        foreach ($mushrooms as $mushroom) {
            $url = $xml->createElement('url');

            $loc = $xml->createElement(
                'loc',
                $this->urlGenerator->generate('mushroom_detail', ['id' => $mushroom->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            );
            $url->appendChild($loc);

            $lastmodDate = $mushroom->getLastModified()?->format('Y-m-d');
            if ($lastmodDate) {
                $url->appendChild($xml->createElement('lastmod', $lastmodDate));
            }

            $url->appendChild($xml->createElement('changefreq', 'monthly'));
            $url->appendChild($xml->createElement('priority', '0.5'));

            $urlset->appendChild($url);
        }

        // Blog posty
        foreach ($posts as $post) {
            $url = $xml->createElement('url');
            $url->appendChild($xml->createElement(
                'loc',
                $this->urlGenerator->generate('blog_show', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)
            ));
            $url->appendChild($xml->createElement('lastmod', $post->getPublishedAt()?->format('Y-m-d') ?? $post->getCreatedAt()->format('Y-m-d')));
            $url->appendChild($xml->createElement('changefreq', 'weekly'));
            $url->appendChild($xml->createElement('priority', '0.7'));
            $urlset->appendChild($url);
        }

        // Tag stránky — len tagy, ktoré majú aspoň 1 publikovaný článok
        foreach ($this->tagRepo->findAllWithPublishedPosts() as ['tag' => $tag, 'lastmod' => $lastmod]) {
            $url = $xml->createElement('url');
            $url->appendChild($xml->createElement(
                'loc',
                $this->urlGenerator->generate('blog_tag', ['slug' => $tag->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)
            ));
            $url->appendChild($xml->createElement('lastmod', $lastmod->format('Y-m-d')));
            $url->appendChild($xml->createElement('changefreq', 'weekly'));
            $url->appendChild($xml->createElement('priority', '0.6'));
            $urlset->appendChild($url);
        }

        $response = new Response($xml->saveXML());
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        // HTTP cache (prispôsob si)
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setSharedMaxAge(3600);

        return $response;
    }

    private function addUrl(\DOMDocument $xml, \DOMElement $urlset, string $route, array $params, ?\DateTimeInterface $lastmod, string $changefreq, string $priority): void
    {
        $url = $xml->createElement('url');
        $url->appendChild($xml->createElement('loc', $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL)));
        if ($lastmod) {
            $url->appendChild($xml->createElement('lastmod', $lastmod->format('Y-m-d')));
        }
        $url->appendChild($xml->createElement('changefreq', $changefreq));
        $url->appendChild($xml->createElement('priority', $priority));
        $urlset->appendChild($url);
    }
}
