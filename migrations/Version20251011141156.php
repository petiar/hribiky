<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011141156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B2030C8D8');
        $this->addSql('DROP INDEX IDX_5C51B18B2030C8D8 ON fotka');
        $this->addSql('ALTER TABLE fotka CHANGE update_entity_id rozcestnik_update_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B3739894D FOREIGN KEY (rozcestnik_update_id) REFERENCES rozcestnik_update (id)');
        $this->addSql('CREATE INDEX IDX_5C51B18B3739894D ON fotka (rozcestnik_update_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B3739894D');
        $this->addSql('DROP INDEX IDX_5C51B18B3739894D ON fotka');
        $this->addSql('ALTER TABLE fotka CHANGE rozcestnik_update_id update_entity_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B2030C8D8 FOREIGN KEY (update_entity_id) REFERENCES rozcestnik_update (id)');
        $this->addSql('CREATE INDEX IDX_5C51B18B2030C8D8 ON fotka (update_entity_id)');
    }
}
