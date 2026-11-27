<?php

namespace App\Controller;

use App\Repository\BlogPostRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    /** Deliteľné 2 aj 3 — mriežka má na tablete 2 a na desktope 3 stĺpce. */
    public const POSTS_PER_PAGE = 12;

    public function __construct(private BlogPostRepository $blogPostRepository)
    {
    }

    #[Route('/blog', name: 'blog_index')]
    public function index(Request $request, BlogPostRepository $repository): Response
    {
        return $this->renderPage(
            $request,
            fn(int $page) => $repository->findPublishedPage($page, self::POSTS_PER_PAGE),
            'blog/index.html.twig',
            'blog_index',
        );
    }

    #[Route('/blog/tag/{slug}', name: 'blog_tag')]
    public function tag(string $slug, Request $request, BlogPostRepository $blogPostRepository, TagRepository $tagRepository): Response
    {
        $tag = $tagRepository->findOneBy(['slug' => $slug]);

        if (!$tag) {
            throw $this->createNotFoundException();
        }

        return $this->renderPage(
            $request,
            fn(int $page) => $blogPostRepository->findByTagPage($slug, $page, self::POSTS_PER_PAGE),
            'blog/tag.html.twig',
            'blog_tag',
            ['slug' => $slug],
            ['tag' => $tag],
        );
    }

    /**
     * Spoločné stránkovanie pre zoznamy článkov (?page=N).
     *
     * @param callable(int): Paginator $loadPage načíta danú stránku
     * @param array<string, mixed> $routeParams parametre route (bez page)
     * @param array<string, mixed> $context ďalšie premenné pre šablónu
     */
    private function renderPage(
        Request $request,
        callable $loadPage,
        string $template,
        string $route,
        array $routeParams = [],
        array $context = [],
    ): Response {
        $page = $request->query->getInt('page', 1);

        // ?page=1 je to isté ako stránka bez parametra — presmeruj, aby neexistovali dve URL s rovnakým obsahom.
        if ($request->query->has('page') && $page === 1) {
            return $this->redirectToRoute($route, $routeParams, Response::HTTP_MOVED_PERMANENTLY);
        }

        $paginator = $loadPage(max($page, 1));
        $totalPosts = count($paginator);
        $totalPages = max(1, (int) ceil($totalPosts / self::POSTS_PER_PAGE));

        // Neplatná alebo neexistujúca stránka (0, -1, za koncom) → 404.
        if ($page < 1 || $page > $totalPages) {
            throw $this->createNotFoundException();
        }

        $posts = iterator_to_array($paginator, false);
        $this->blogPostRepository->preloadPhotosAndTags($posts);

        return $this->render($template, $context + [
            'posts' => $posts,
            'totalPosts' => $totalPosts,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_show')]
    public function show(string $slug, BlogPostRepository $repository): Response
    {
        $post = $repository->findOneBy(['slug' => $slug]);

        if (!$post || !$post->isPublished()) {
            throw $this->createNotFoundException();
        }

        // Fotky súvisiacich článkov jedným dotazom (inak dotaz pre každý článok).
        $related = $repository->findRelated($post);
        $repository->preloadPhotosAndTags($related);

        return $this->render('blog/show.html.twig', [
            'post' => $post,
            'related' => $related,
        ]);
    }
}