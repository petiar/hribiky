<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\BadgeType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBadge::class);
    }

    public function findByType(BadgeType $type): ?UserBadge
    {
        return $this->findOneBy(['type' => $type]);
    }

    public function findByUserAndType(User $user, BadgeType $type): ?UserBadge
    {
        return $this->findOneBy(['user' => $user, 'type' => $type]);
    }

    /**
     * @param User[] $users
     * @return UserBadge[]
     */
    public function findForUsers(array $users): array
    {
        if (empty($users)) {
            return [];
        }

        return $this->createQueryBuilder('ub')
            ->andWhere('ub.user IN (:users)')
            ->setParameter('users', $users)
            ->getQuery()
            ->getResult();
    }

    /**
     * Odznaky zoskupené podľa ID usera, v každej skupine zoradené podľa poradia v BadgeType.
     *
     * @param User[] $users
     * @return array<int, UserBadge[]>
     */
    public function findGroupedByUserId(array $users): array
    {
        $order = array_flip(array_map(fn(BadgeType $type) => $type->value, BadgeType::cases()));

        $grouped = [];
        foreach ($this->findForUsers($users) as $badge) {
            $grouped[$badge->getUser()->getId()][] = $badge;
        }

        foreach ($grouped as &$badges) {
            usort($badges, fn(UserBadge $a, UserBadge $b) => $order[$a->getType()->value] <=> $order[$b->getType()->value]);
        }
        unset($badges);

        return $grouped;
    }
}
