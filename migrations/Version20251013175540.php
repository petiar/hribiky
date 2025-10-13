<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013175540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B3739894D');
        $this->addSql('ALTER TABLE fotka DROP FOREIGN KEY FK_5C51B18B56B078A6');
        $this->addSql('RENAME TABLE fotka TO photo');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B7841856B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES mushroom (id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784183739894D FOREIGN KEY (rozcestnik_update_id) REFERENCES mushroom_comment (id)');
        $this->addSql('ALTER TABLE mushroom_comment RENAME INDEX idx_1fc5e19056b078a6 TO IDX_281657E256B078A6');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B7841856B078A6');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784183739894D');
        $this->addSql('RENAME TABLE photo TO fotka');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B3739894D FOREIGN KEY (rozcestnik_update_id) REFERENCES mushroom_comment (id)');
        $this->addSql('ALTER TABLE fotka ADD CONSTRAINT FK_5C51B18B56B078A6 FOREIGN KEY (rozcestnik_id) REFERENCES mushroom (id)');
        $this->addSql('ALTER TABLE mushroom_comment RENAME INDEX idx_281657e256b078a6 TO IDX_1FC5E19056B078A6');
    }
}
