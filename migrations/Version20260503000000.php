<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pridá stĺpec comment_count do tabuľky user pre leaderboard skóre';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD comment_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP comment_count');
    }
}