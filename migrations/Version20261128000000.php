<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Poloha hríbika slovami: obec, okres, kraj a pohorie (dopĺňa app:resolve-locations)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('mushroom');
        foreach (['locality', 'district', 'region', 'mountain_range', 'mountain_range_cs'] as $column) {
            if (!$table->hasColumn($column)) {
                $table->addColumn($column, Types::STRING, ['length' => 128, 'notnull' => false]);
            }
        }
        if (!$table->hasColumn('mountain_range_distance')) {
            $table->addColumn('mountain_range_distance', Types::INTEGER, ['notnull' => false]);
        }
        if (!$table->hasColumn('location_resolved_at')) {
            $table->addColumn('location_resolved_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('mushroom');
        foreach (['locality', 'district', 'region', 'mountain_range', 'mountain_range_cs', 'mountain_range_distance', 'location_resolved_at'] as $column) {
            if ($table->hasColumn($column)) {
                $table->dropColumn($column);
            }
        }
    }
}
