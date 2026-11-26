<?php

namespace App\Controller;

use App\Entity\BlogPost;
use App\Entity\Mushroom;
use App\Service\MushroomApiFormatter;
use App\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Vyhľadávanie pre mobilnú aplikáciu. Rovnaká logika ako webové /hladat (SearchService).
 *
 * Spätná kompatibilita: odpoveď sa smie len rozširovať (viď docs/api/README.md).
 * Verejný endpoint (bez API kľúča) — vracia len verejné údaje, rovnaké ako webové vyhľadávanie.
 */
class SearchApiController extends AbstractController
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 50;

    public function __construct(
        private readonly SearchService $searchService,
        private readonly MushroomApiFormatter $mushroomFormatter,
    ) {
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $this->searchService->normalizeQuery($request->query->getString('q'));
        $limit = min(max($request->query->getInt('limit', self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
        $valid = $this->searchService->isQueryValid($query);

        // O jeden viac, aby sme vedeli, či existujú ďalšie výsledky.
        $mushrooms = $valid ? $this->searchService->searchMushrooms($query, $limit + 1) : [];
        $posts = $valid ? $this->searchService->searchBlogPosts($query, $limit + 1) : [];

        $baseUrl = $request->getSchemeAndHttpHost();

        return $this->json([
            'query' => $query,
            'min_query_length' => SearchService::MIN_QUERY_LENGTH,
            'mushrooms' => array_map(
                fn(Mushroom $mushroom) => $this->mushroomFormatter->mushroom($mushroom, $baseUrl),
                array_slice($mushrooms, 0, $limit)
            ),
            'mushrooms_has_more' => count($mushrooms) > $limit,
            'posts' => array_map(
                fn(BlogPost $post) => $this->formatPost($post, $baseUrl),
                array_slice($posts, 0, $limit)
            ),
            'posts_has_more' => count($posts) > $limit,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPost(BlogPost $post, string $baseUrl): array
    {
        $photo = $post->getPhotos()->first() ?: null;

        return [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'short_description' => $post->getShortDescription(),
            'published_at' => $post->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            // Appka zatiaľ nemá API na čítanie článkov — článok otvorí cez URL (WebView / prehliadač).
            'url' => $this->generateUrl('blog_show', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            'image_url' => $photo ? $baseUrl . '/uploads/photos/' . $photo->getPath() : null,
        ];
    }
}
