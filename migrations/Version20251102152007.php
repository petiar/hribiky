<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251102152007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mushroom_edit_link (id INT AUTO_INCREMENT NOT NULL, mushroom_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent_to_email VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_5200DB3AE3FCCFFC (mushroom_id), UNIQUE INDEX uniq_mushroom_editlink_token (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mushroom_edit_link ADD CONSTRAINT FK_5200DB3AE3FCCFFC FOREIGN KEY (mushroom_id) REFERENCES mushroom (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mushroom_edit_link DROP FOREIGN KEY FK_5200DB3AE3FCCFFC');
        $this->addSql('DROP TABLE mushroom_edit_link');
    }
}
