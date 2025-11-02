<?php

// src/Controller/SitemapController.php
namespace App\Controller;

use App\Repository\MushroomRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private MushroomRepository $mushroomRepo,
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

        $response = new Response($xml->saveXML());
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        // HTTP cache (prispôsob si)
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setSharedMaxAge(3600);

        return $response;
    }
}
