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
                    e.email,
                    COALESCE(m.mushroom_cnt, 0) AS mushroom_cnt,
                    COALESCE(c.comment_cnt, 0) AS comment_cnt,
                    (
                        SELECT m2.name
                        FROM mushroom m2
                        WHERE m2.email = e.email
                          AND m2.published = 1
                        GROUP BY m2.name
                        ORDER BY COUNT(*) DESC, MAX(m2.id) DESC
                        LIMIT 1
                    ) AS preferred_name
                FROM (
                    SELECT email FROM mushroom WHERE email IS NOT NULL AND email <> '' AND published = 1
                    UNION
                    SELECT email FROM mushroom_comment WHERE email IS NOT NULL AND email <> '' AND published = 1
                ) e
                LEFT JOIN (
                    SELECT email, COUNT(*) AS mushroom_cnt
                    FROM mushroom
                    WHERE email IS NOT NULL AND email <> '' AND published = 1
                    GROUP BY email
                ) m ON m.email = e.email
                LEFT JOIN (
                    SELECT email, COUNT(*) AS comment_cnt
                    FROM mushroom_comment
                    WHERE email IS NOT NULL AND email <> '' AND published = 1
                    GROUP BY email
                ) c ON c.email = e.email;
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql);

        $affected = 0;

        $this->entityManager->wrapInTransaction(function () use ($rows, &$affected) {
            foreach ($rows as $row) {
                $email = (string)$row['email'];
                $mushroomCnt = (int)$row['mushroom_cnt'];
                $commentCnt = (int)$row['comment_cnt'];
                $username = $row['preferred_name'] !== null ? (string)$row['preferred_name'] : null;

                $user = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setName($username);
                    $user->setMushroomCount($mushroomCnt);
                    $user->setCommentCount($commentCnt);
                    $user->setPassword('disabled');
                    $this->entityManager->persist($user);
                    $affected++;
                } else {
                    $prevMushroomCnt = $user->getMushroomCount();
                    $prevUsername = $user->getName();

                    $changed = false;
                    if ($prevMushroomCnt !== $mushroomCnt) {
                        $user->setMushroomCount($mushroomCnt);
                        if ($mushroomCnt >= User::ROLE_RELIABLE_TRESHOLD) {
                            $user->addRole('ROLE_RELIABLE');
                        }
                        if (($prevMushroomCnt > $mushroomCnt) && ($mushroomCnt < User::ROLE_RELIABLE_TRESHOLD)) {
                            $user->removeRole('ROLE_RELIABLE');
                        }
                        $changed = true;
                    }
                    if ($user->getCommentCount() !== $commentCnt) {
                        $user->setCommentCount($commentCnt);
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
