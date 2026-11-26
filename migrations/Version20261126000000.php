<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pridá stĺpec description_score_attempts na mushroom (počet neúspešných pokusov o AI ohodnotenie popisu)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom ADD description_score_attempts SMALLINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom DROP description_score_attempts');
    }
}
