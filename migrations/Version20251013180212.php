<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013180212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mushroom_comment DROP FOREIGN KEY FK_1FC5E19056B078A6');
        $this->addSql('DROP INDEX IDX_281657E256B078A6 ON mushroom_comment');
        $this->addSql('ALTER TABLE mushroom_comment CHANGE rozcestnik_id mushroom_id INT NOT NULL');
        $this->addSql('ALTER TABLE mushroom_comment ADD CONSTRAINT FK_281657E2E3FCCFFC FOREIGN KEY (mushroom_id) REFERENCES mushroom (id)');
        $this->addSql('CREATE INDEX IDX_281657E2E3FCCFFC ON mushroom_comment (mushroom_id)');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_5c51b18b56b078a6 TO IDX_14B7841856B078A6');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_5c51b18b3739894d TO IDX_14B784183739894D');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mushroom_comment DROP FOREIGN KEY FK_281657E2E3FCCFFC');
        $this->addSql('DROP INDEX IDX_281657E2E3FCCFFC ON mushroom_comment');
        $this->addSql('ALTER TABLE mushroom_comment CHANGE mushroom_id rozcestnik_id INT NOT NULL');
        $this->addSql('ALTER TABLE mushroom_comment ADD CONSTRAINT FK_1FC5E19056B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES mushroom (id)');
        $this->addSql('CREATE INDEX IDX_281657E256B078A6 ON mushroom_comment (rozcestnik_id)');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_14b784183739894d TO IDX_5C51B18B3739894D');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_14b7841856b078a6 TO IDX_5C51B18B56B078A6');
    }
}
