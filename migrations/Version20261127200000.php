<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pridá nepovinný e-mail odosielateľa do feedback (formulár spätnej väzby)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('feedback');
        if (!$table->hasColumn('email')) {
            $table->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('feedback');
        if ($table->hasColumn('email')) {
            $table->dropColumn('email');
        }
    }
}
