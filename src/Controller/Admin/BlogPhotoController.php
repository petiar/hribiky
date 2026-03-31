<?php

namespace App\Controller\Admin;

use App\Entity\BlogPost;
use App\Entity\Photo;
use App\Service\FotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class BlogPhotoController extends AbstractController
{
    public function __construct(
        private FotoUploader $fotoUploader,
        private EntityManagerInterface $em,
        private string $uploadDir,
    ) {}

    #[Route('/admin/blog-post/{id}/photos', name: 'admin_blog_photo_list', methods: ['GET'])]
    public function list(BlogPost $blogPost): JsonResponse
    {
        return $this->json($this->photosToArray($blogPost->getPhotos()->toArray()));
    }

    #[Route('/admin/blog-post/{id}/photos', name: 'admin_blog_photo_upload', methods: ['POST'])]
    public function upload(Request $request, BlogPost $blogPost): JsonResponse
    {
        $files = $request->files->get('files', []);
        if (empty($files)) {
            return $this->json(['error' => 'Žiadne súbory'], 400);
        }

        $this->fotoUploader->uploadAndAttach($files, $blogPost);
        $this->em->flush();

        return $this->json($this->photosToArray($blogPost->getPhotos()->toArray()));
    }

    #[Route('/admin/blog-post/temp-photos', name: 'admin_blog_photo_temp_upload', methods: ['POST'])]
    public function tempUpload(Request $request): JsonResponse
    {
        $files = $request->files->get('files', []);
        if (empty($files)) {
            return $this->json(['error' => 'Žiadne súbory'], 400);
        }

        $photos = [];
        foreach ($files as $file) {
            $filename = uniqid() . '.' . $file->guessExtension();
            $file->move($this->uploadDir, $filename);

            $photo = new Photo();
            $photo->setPath($filename);
            $photo->setOwner(BlogPost::class);
            $this->em->persist($photo);
            $photos[] = $photo;
        }

        $this->em->flush();

        return $this->json($this->photosToArray($photos));
    }

    private function photosToArray(array $photos): array
    {
        return array_values(array_map(fn(Photo $p) => [
            'id'  => $p->getId(),
            'url' => '/uploads/photos/' . $p->getPath(),
        ], $photos));
    }
}