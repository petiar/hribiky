<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Doctrine DBAL 4 už nepoužíva komentáre „(DC2Type:…)“ pri stĺpcoch (DBAL 3 nimi označoval
 * typy ako datetime_immutable či json). Po aktualizácii by ich schema:validate hlásil ako rozdiel.
 *
 * Neberie napevno zoznam stĺpcov — odstráni takéto komentáre všade, kde v databáze sú,
 * takže funguje na lokálnej MySQL aj produkčnej MariaDB, aj keď sa v detailoch líšia.
 * Iné komentáre (ak by nejaké boli) nechá tak.
 */
final class Version20261127100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Odstránenie komentárov (DC2Type:…) zo stĺpcov — DBAL 4 ich už nepoužíva';
    }

    public function isTransactional(): bool
    {
        // ALTER TABLE v MySQL/MariaDB aj tak transakciu ukončí.
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach ($schema->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                if (preg_match('/^\(DC2Type:[a-z_]+\)$/i', $column->getComment())) {
                    $column->setComment('');
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Komentáre DBAL 4 nepotrebuje ani nečíta; späť ich nedopĺňame.
    }
}
