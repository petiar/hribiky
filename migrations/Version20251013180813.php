<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013180813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784183739894D');
        $this->addSql('DROP INDEX IDX_14B784183739894D ON photo');
        $this->addSql('ALTER TABLE photo CHANGE rozcestnik_update_id mushroom_comment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B78418CD09BF14 FOREIGN KEY (mushroom_comment_id) REFERENCES mushroom_comment (id)');
        $this->addSql('CREATE INDEX IDX_14B78418CD09BF14 ON photo (mushroom_comment_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B78418CD09BF14');
        $this->addSql('DROP INDEX IDX_14B78418CD09BF14 ON photo');
        $this->addSql('ALTER TABLE photo CHANGE mushroom_comment_id rozcestnik_update_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784183739894D FOREIGN KEY (rozcestnik_update_id) REFERENCES mushroom_comment (id)');
        $this->addSql('CREATE INDEX IDX_14B784183739894D ON photo (rozcestnik_update_id)');
    }
}
