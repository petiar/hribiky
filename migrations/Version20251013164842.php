<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013164842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B56B078A6');
        $this->addSql('ALTER TABLE rozcestnik_update DROP FOREIGN KEY FK_1FC5E19056B078A6');
        $this->addSql('RENAME TABLE rozcestnik TO mushroom');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B56B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES mushroom (id)');
        $this->addSql('ALTER TABLE rozcestnik_update ADD CONSTRAINT FK_1FC5E19056B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES mushroom (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B56B078A6');
        $this->addSql('ALTER TABLE rozcestnik_update DROP FOREIGN KEY FK_1FC5E19056B078A6');
        $this->addSql('RENAME TABLE mushroom TO rozcestnik;');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B56B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES rozcestnik (id)');
        $this->addSql('ALTER TABLE rozcestnik_update ADD CONSTRAINT FK_1FC5E19056B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES rozcestnik (id)');
    }
}
