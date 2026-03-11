<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create blog_post table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blog_post (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            short_description LONGTEXT NOT NULL,
            text LONGTEXT NOT NULL,
            published_at DATETIME DEFAULT NULL,
            published TINYINT(1) NOT NULL,
            tags JSON NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blog_post');
    }
}