<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/leaderboard', name: 'api_leaderboard_')]
class LeaderboardApiController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $userRepository): JsonResponse
    {
        $contributors = $userRepository->findTopContributors(100);

        $data = array_map(fn($user) => [
            'name' => $user->getName(),
            'mushroom_count' => $user->getMushroomCount(),
            'comment_count' => $user->getCommentCount(),
            'score' => $user->getScore(),
        ], $contributors);

        return $this->json($data);
    }
}