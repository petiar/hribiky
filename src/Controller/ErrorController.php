<?php

namespace App\Controller;

use App\Entity\Mushroom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ErrorController extends AbstractController
{
    public function show(\Throwable $exception, EntityManagerInterface $entityManager): Response
    {
        $statusCode = method_exists($exception, 'getStatusCode')
            ? $exception->getStatusCode()
            : 500;

        if ($statusCode === 404) {
            return $this->render('error/error404.html.twig', [
                'mushroom' => $this->getRandomMushroom($entityManager),
            ]);
        }

        // Pre ostatné chyby štandardné zobrazenie
        return $this->render('bundles/TwigBundle/Exception/error.html.twig', [
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? 'Error',
        ]);
    }

    private function getRandomMushroom(EntityManagerInterface $entityManager): ?Mushroom
    {
        $mushrooms = $entityManager->getRepository(Mushroom::class)
            ->createQueryBuilder('m')
            ->where('m.published = :published')
            ->setParameter('published', true)
            ->getQuery()
            ->getResult();

        if (empty($mushrooms)) {
            return null;
        }

        return $mushrooms[array_rand($mushrooms)];
    }
}
