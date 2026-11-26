<?php

namespace App\Controller;

use App\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    /** Najviac toľko výsledkov z každej skupiny (hríbiky, blog). */
    public const RESULTS_LIMIT = 50;

    /** Počet návrhov v autocomplete. */
    public const SUGGEST_MUSHROOMS = 5;
    public const SUGGEST_POSTS = 3;

    public function __construct(
        private readonly SearchService $searchService,
    ) {
    }

    #[Route('/hladat', name: 'search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $this->searchService->normalizeQuery($request->query->getString('q'));
        $valid = $this->searchService->isQueryValid($query);

        // O jeden viac, aby sme vedeli, či sú výsledky orezané.
        $mushrooms = $valid ? $this->searchService->searchMushrooms($query, self::RESULTS_LIMIT + 1) : [];
        $posts = $valid ? $this->searchService->searchBlogPosts($query, self::RESULTS_LIMIT + 1) : [];

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'queryValid' => $valid,
            'minLength' => SearchService::MIN_QUERY_LENGTH,
            'mushrooms' => array_slice($mushrooms, 0, self::RESULTS_LIMIT),
            'mushroomsTruncated' => count($mushrooms) > self::RESULTS_LIMIT,
            'posts' => array_slice($posts, 0, self::RESULTS_LIMIT),
            'postsTruncated' => count($posts) > self::RESULTS_LIMIT,
            'limit' => self::RESULTS_LIMIT,
        ]);
    }

    /**
     * Návrhy pre autocomplete vo vyhľadávacom poli v menu.
     * Verejný endpoint — vracia len verejné údaje (názov, odkaz, výška/dátum).
     */
    #[Route('/hladat/navrhy', name: 'search_suggest', methods: ['GET'])]
    public function suggest(Request $request): JsonResponse
    {
        $query = $this->searchService->normalizeQuery($request->query->getString('q'));

        $items = [];
        if ($this->searchService->isQueryValid($query)) {
            foreach ($this->searchService->searchMushrooms($query, self::SUGGEST_MUSHROOMS) as $mushroom) {
                $items[] = [
                    'type' => 'mushroom',
                    'title' => $mushroom->getTitle(),
                    'subtitle' => $mushroom->getAltitude() !== null ? $mushroom->getAltitude() . ' m n. m.' : null,
                    'url' => $this->generateUrl('mushroom_detail', ['id' => $mushroom->getId()]),
                ];
            }
            foreach ($this->searchService->searchBlogPosts($query, self::SUGGEST_POSTS) as $post) {
                $items[] = [
                    'type' => 'post',
                    'title' => $post->getTitle(),
                    'subtitle' => $post->getPublishedAt()?->format('d.m.Y'),
                    'url' => $this->generateUrl('blog_show', ['slug' => $post->getSlug()]),
                ];
            }
        }

        $response = $this->json([
            'query' => $query,
            'items' => $items,
            'searchUrl' => $this->generateUrl('search', ['q' => $query]),
        ]);
        // Rovnaký dotaz počas písania/mazania sa opakuje — krátka cache v prehliadači.
        $response->setPrivate();
        $response->setMaxAge(60);

        return $response;
    }
}
