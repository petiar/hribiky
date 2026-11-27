<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RobotsController extends AbstractController
{
    /**
     * robots.txt generovaný podľa domény — hribiky.sk aj hribiky.cz zdieľajú jeden adresár public/,
     * ale každá doména musí odkazovať na vlastnú sitemapu (s vlastnými adresami).
     *
     * Stránky s noindex (vyhľadávanie, editačné odkazy) tu zámerne NIE SÚ zakázané — keby boli,
     * vyhľadávač by noindex nevidel a mohol by adresu zaindexovať aj bez obsahu.
     */
    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function __invoke(UrlGeneratorInterface $urlGenerator): Response
    {
        $sitemap = $urlGenerator->generate('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = new Response(implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /api/',
            '',
            'Sitemap: ' . $sitemap,
            '',
        ]));
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
