<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013173842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B3739894D');
        $this->addSql('RENAME TABLE rozcestnik_update TO mushroom_comment');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B3739894D FOREIGN KEY (rozcestnik_update_id) REFERENCES mushroom_comment (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B3739894D');
        $this->addSql('RENAME TABLE mushroom_comment TO rozcestnik_update');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B3739894D FOREIGN KEY (rozcestnik_update_id) REFERENCES rozcestnik_update (id)');
    }
}
