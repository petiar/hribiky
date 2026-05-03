<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vytvorí tabuľku mushroom_comment_edit_link pre jednorazové edit odkazy ku komentárom';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mushroom_comment_edit_link (
            id INT AUTO_INCREMENT NOT NULL,
            mushroom_comment_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            sent_to_email VARCHAR(255) DEFAULT NULL,
            UNIQUE INDEX uniq_mushroom_comment_editlink_token (token_hash),
            INDEX IDX_mushroom_comment_id (mushroom_comment_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE mushroom_comment_edit_link ADD CONSTRAINT FK_mushroom_comment_edit_link_comment FOREIGN KEY (mushroom_comment_id) REFERENCES mushroom_comment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom_comment_edit_link DROP FOREIGN KEY FK_mushroom_comment_edit_link_comment');
        $this->addSql('DROP TABLE mushroom_comment_edit_link');
    }
}