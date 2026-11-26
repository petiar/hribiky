<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vytvorí tabuľku user_badge pre gamifikačné odznaky a pridá description_score/description_scored_at na mushroom';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_badge (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            evidence_mushroom_id INT DEFAULT NULL,
            type VARCHAR(32) NOT NULL,
            value DOUBLE PRECISION DEFAULT NULL,
            awarded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_user_badge_user_type (user_id, type),
            INDEX IDX_user_badge_user (user_id),
            INDEX IDX_user_badge_evidence_mushroom (evidence_mushroom_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_user_badge_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_user_badge_evidence_mushroom FOREIGN KEY (evidence_mushroom_id) REFERENCES mushroom (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE mushroom ADD description_score INT DEFAULT NULL, ADD description_scored_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom DROP description_score, DROP description_scored_at');

        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_user_badge_evidence_mushroom');
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_user_badge_user');
        $this->addSql('DROP TABLE user_badge');
    }
}
