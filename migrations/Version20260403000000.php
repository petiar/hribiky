<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pridá stĺpec approval_token na tabuľku mushroom pre jednorazové schvaľovacie odkazy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mushroom ADD approval_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_mushroom_approval_token ON mushroom (approval_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_mushroom_approval_token ON mushroom');
        $this->addSql('ALTER TABLE mushroom DROP COLUMN approval_token');
    }
}