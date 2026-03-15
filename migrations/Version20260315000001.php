<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vytvorí entitu Tag, join tabuľku blog_post_tag a migruje existujúce JSON tagy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tag (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            UNIQUE INDEX UNIQ_389B7835E237E06 (name),
            UNIQUE INDEX UNIQ_389B783989D9B62 (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE blog_post_tag (
            blog_post_id INT NOT NULL,
            tag_id INT NOT NULL,
            INDEX IDX_9BB3B0B3A77FBEAF (blog_post_id),
            INDEX IDX_9BB3B0B3BAD26311 (tag_id),
            PRIMARY KEY(blog_post_id, tag_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE blog_post_tag
            ADD CONSTRAINT FK_9BB3B0B3A77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_9BB3B0B3BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function postUp(Schema $schema): void
    {
        $posts = $this->connection->fetchAllAssociative(
            'SELECT id, tags FROM blog_post WHERE tags IS NOT NULL AND tags != :empty',
            ['empty' => '[]']
        );

        foreach ($posts as $post) {
            $tagNames = json_decode($post['tags'], true);
            if (!is_array($tagNames)) {
                continue;
            }

            foreach ($tagNames as $tagName) {
                $tagName = trim((string) $tagName);
                if ($tagName === '') {
                    continue;
                }

                $slug = $this->slugify($tagName);

                $existing = $this->connection->fetchOne('SELECT id FROM tag WHERE slug = ?', [$slug]);
                if (!$existing) {
                    $this->connection->executeStatement(
                        'INSERT INTO tag (name, slug) VALUES (?, ?)',
                        [$tagName, $slug]
                    );
                    $tagId = (int) $this->connection->lastInsertId();
                } else {
                    $tagId = (int) $existing;
                }

                $this->connection->executeStatement(
                    'INSERT IGNORE INTO blog_post_tag (blog_post_id, tag_id) VALUES (?, ?)',
                    [(int) $post['id'], $tagId]
                );
            }
        }

        $this->connection->executeStatement('ALTER TABLE blog_post DROP COLUMN tags');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_post ADD tags JSON NOT NULL');
        $this->addSql('ALTER TABLE blog_post_tag DROP FOREIGN KEY FK_9BB3B0B3A77FBEAF');
        $this->addSql('ALTER TABLE blog_post_tag DROP FOREIGN KEY FK_9BB3B0B3BAD26311');
        $this->addSql('DROP TABLE blog_post_tag');
        $this->addSql('DROP TABLE tag');
    }

    private function slugify(string $text): string
    {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}
