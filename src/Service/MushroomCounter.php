<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class MushroomCounter
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection
    ) {
    }

    public function countMushrooms(): int
    {
        $sql = <<<SQL
                SELECT
                m.email,
                COUNT(*) AS cnt,
                (
                    SELECT m2.name
                    FROM mushroom m2
                    WHERE m2.email = m.email
                      AND m2.published = 1
                    GROUP BY m2.name
                    ORDER BY COUNT(*) DESC, MAX(m2.id) DESC
                    LIMIT 1
                  ) AS preferred_name
                FROM mushroom m
                WHERE m.email IS NOT NULL
                  AND m.email <> ''
                  AND m.published = 1
                GROUP BY m.email;
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql);

        $affected = 0;

        $this->entityManager->wrapInTransaction(function () use ($rows, &$affected) {
            foreach ($rows as $row) {
                $email = (string)$row['email'];
                $count = (int)$row['cnt'];
                $username = $row['preferred_name'] !== null ? (string)$row['preferred_name'] : null;

                $user = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setName($username);
                    $user->setMushroomCount($count);
                    $user->setPassword('disabled');
                    $this->entityManager->persist($user);
                    $affected++;
                } else {
                    $prevCount = $user->getMushroomCount();
                    $prevUsername = $user->getName();

                    $changed = false;
                    if ($prevCount !== $count) {
                        $user->setMushroomCount($count);
                        if ($count >= User::ROLE_RELIABLE_TRESHOLD) {
                            $user->addRole('ROLE_RELIABLE');
                        }
                        if (($prevCount > $count) && ($count < User::ROLE_RELIABLE_TRESHOLD)) {
                            $user->removeRole('ROLE_RELIABLE');
                        }
                        $changed = true;
                    }
                    if (!$prevUsername && $username) {
                        $user->setName($username);
                        $changed = true;
                    }
                    if ($changed) {
                        $affected++;
                    }
                }
            }
        });

        return $affected;
    }
}
