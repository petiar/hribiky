<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zmení typ stĺpca altitude z FLOAT na INT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom MODIFY altitude INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom MODIFY altitude FLOAT DEFAULT NULL');
    }
}