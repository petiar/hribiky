<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pridá tabuľku mushroom_article_link pre prepojenie hríbikov s článkami (interné aj externé)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mushroom_article_link (
            id INT AUTO_INCREMENT NOT NULL,
            mushroom_id INT NOT NULL,
            blog_post_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            url LONGTEXT NOT NULL,
            INDEX IDX_MUSHROOM_ARTICLE_MUSHROOM (mushroom_id),
            INDEX IDX_MUSHROOM_ARTICLE_BLOG_POST (blog_post_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE mushroom_article_link
            ADD CONSTRAINT FK_MUSHROOM_ARTICLE_MUSHROOM FOREIGN KEY (mushroom_id) REFERENCES mushroom (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_MUSHROOM_ARTICLE_BLOG_POST FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom_article_link DROP FOREIGN KEY FK_MUSHROOM_ARTICLE_MUSHROOM');
        $this->addSql('ALTER TABLE mushroom_article_link DROP FOREIGN KEY FK_MUSHROOM_ARTICLE_BLOG_POST');
        $this->addSql('DROP TABLE mushroom_article_link');
    }
}