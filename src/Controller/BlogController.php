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

        $posts = $loadPage(max($page, 1));
        $totalPages = max(1, (int) ceil(count($posts) / self::POSTS_PER_PAGE));

        // Neplatná alebo neexistujúca stránka (0, -1, za koncom) → 404.
        if ($page < 1 || $page > $totalPages) {
            throw $this->createNotFoundException();
        }

        return $this->render($template, $context + [
            'posts' => $posts,
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

        return $this->render('blog/show.html.twig', [
            'post' => $post,
            'related' => $repository->findRelated($post),
        ]);
    }
}