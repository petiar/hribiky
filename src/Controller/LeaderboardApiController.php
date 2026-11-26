<?php

namespace App\Controller;

use App\Entity\UserBadge;
use App\Enum\BadgeType;
use App\Repository\UserBadgeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Spätná kompatibilita s mobilnou aplikáciou: odpovede sa len ROZŠIRUJÚ.
 * Existujúce polia a tvar odpovede (pole na najvyššej úrovni) sa nesmú meniť ani odoberať —
 * staršie verzie appky neznáme kľúče ignorujú, ale chýbajúce/zmenené kľúče by ich rozbili.
 */
#[Route('/api/leaderboard', name: 'api_leaderboard_')]
class LeaderboardApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserBadgeRepository $userBadgeRepository,
        private readonly Packages $packages,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $contributors = $this->userRepository->findTopContributors(100);
        $badgesByUserId = $this->userBadgeRepository->findGroupedByUserId($contributors);

        $data = array_map(fn($user) => [
            'name' => $user->getName(),
            'mushroom_count' => $user->getMushroomCount(),
            'comment_count' => $user->getCommentCount(),
            'score' => $user->getScore(),
            // Pridané s odznakmi — staršie verzie appky toto pole ignorujú.
            'badges' => array_map(
                fn(UserBadge $badge) => $this->serializeUserBadge($badge, $request),
                $badgesByUserId[$user->getId()] ?? []
            ),
        ], $contributors);

        return $this->json($data);
    }

    /**
     * Zoznam všetkých odznakov (vysvetlivky), v rovnakom poradí ako na webe.
     */
    #[Route('/badges', name: 'badges', methods: ['GET'])]
    public function badges(Request $request): JsonResponse
    {
        $data = array_map(fn(BadgeType $type) => [
            'type' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'is_trophy' => $type->isTrophy(),
            'icon_url' => $this->iconUrl($type, $request),
        ], BadgeType::cases());

        return $this->json($data);
    }

    private function serializeUserBadge(UserBadge $badge, Request $request): array
    {
        return [
            'type' => $badge->getType()->value,
            'label' => $badge->getType()->label(),
            'icon_url' => $this->iconUrl($badge->getType(), $request),
            'value' => $badge->getValue(),
            'awarded_at' => $badge->getAwardedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function iconUrl(BadgeType $type, Request $request): string
    {
        return $request->getSchemeAndHttpHost()
            . $this->packages->getUrl('images/badges/' . $type->iconSlug() . '.svg');
    }
}
