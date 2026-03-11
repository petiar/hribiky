<?php

namespace App\Controller;

use App\Repository\BlogPostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    #[Route('/blog', name: 'blog_index')]
    public function index(BlogPostRepository $repository): Response
    {
        $posts = $repository->findAllPublished();

        return $this->render('blog/index.html.twig', [
            'posts' => $posts,
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