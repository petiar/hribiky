<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blog_post_generated flag to mushroom table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom ADD blog_post_generated TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom DROP blog_post_generated');
    }
}