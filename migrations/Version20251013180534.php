<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013180534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B7841856B078A6');
        $this->addSql('DROP INDEX IDX_14B7841856B078A6 ON photo');
        $this->addSql('ALTER TABLE photo CHANGE rozcestnik_id mushroom_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B78418E3FCCFFC FOREIGN KEY (mushroom_id) REFERENCES mushroom (id)');
        $this->addSql('CREATE INDEX IDX_14B78418E3FCCFFC ON photo (mushroom_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B78418E3FCCFFC');
        $this->addSql('DROP INDEX IDX_14B78418E3FCCFFC ON photo');
        $this->addSql('ALTER TABLE photo CHANGE mushroom_id rozcestnik_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B7841856B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES mushroom (id)');
        $this->addSql('CREATE INDEX IDX_14B7841856B078A6 ON photo (rozcestnik_id)');
    }
}
