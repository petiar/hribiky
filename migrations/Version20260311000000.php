<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blog_post_id to photo table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo ADD blog_post_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B78418448A93E FOREIGN KEY (blog_post_id) REFERENCES blog_post (id)');
        $this->addSql('CREATE INDEX IDX_14B78418448A93E ON photo (blog_post_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B78418448A93E');
        $this->addSql('DROP INDEX IDX_14B78418448A93E ON photo');
        $this->addSql('ALTER TABLE photo DROP blog_post_id');
    }
}