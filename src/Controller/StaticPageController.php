<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StaticPageController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserRepository $userRepository
    )
    {}

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('static_page/about.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('static_page/contact.html.twig');
    }

    #[Route('/privacy/{lang}', name: 'privacy', defaults: ['lang' => 'sk'])]
    public function privacy(string $lang): Response
    {
        return $this->render('static_page/privacy.html.twig', [
            'lang' => $this->resolvePrivacyLang($lang),
        ]);
    }

    #[Route('/api/privacy/{lang}', name: 'api_privacy', defaults: ['lang' => 'sk'], methods: ['GET'])]
    public function privacyApi(string $lang): Response
    {
        return $this->render(
            sprintf('static_page/privacy_text.%s.html.twig', $this->resolvePrivacyLang($lang))
        );
    }

    private function resolvePrivacyLang(string $lang): string
    {
        return in_array($lang, ['sk', 'cs', 'en']) ? $lang : 'sk';
    }

    #[Route('/leaderboard', name: 'app_leaderboard', methods: ['GET'])]
    public function leaderboard(): Response
    {
        $top = $this->userRepository->findTopContributors(100); // zmeň limit podľa potreby

        return $this->render('leaderboard/index.html.twig', [
            'contributors' => $top,
        ]);
    }
}
