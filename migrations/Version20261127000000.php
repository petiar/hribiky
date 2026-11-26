<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zosúladí schému databázy s entitami (doctrine:schema:validate hlásil rozdiely).
 *
 * Píše sa cez Schema API (nie surové SQL) a každá zmena sa spraví len vtedy,
 * keď ju daná databáza potrebuje — SQL vygeneruje Doctrine pre server, na ktorom
 * migrácia beží, a ak má produkcia niečo inak ako lokál, deploy nespadne.
 */
final class Version20261127000000 extends AbstractMigration
{
    /** tabuľka → [starý názov indexu → názov, ktorý generuje Doctrine] */
    private const INDEX_RENAMES = [
        'user_badge' => [
            'idx_user_badge_user' => 'IDX_1C32B345A76ED395',
            'idx_user_badge_evidence_mushroom' => 'IDX_1C32B34538CDC403',
        ],
        'blog_post_tag' => [
            'idx_9bb3b0b3a77fbeaf' => 'IDX_2E931ED7A77FBEAF',
            'idx_9bb3b0b3bad26311' => 'IDX_2E931ED7BAD26311',
        ],
        'mushroom_comment_edit_link' => [
            'idx_mushroom_comment_id' => 'IDX_A95439ABCD09BF14',
        ],
        'photo' => [
            'idx_14b78418448a93e' => 'IDX_14B78418A77FBEAF',
        ],
        'mushroom' => [
            'uniq_mushroom_approval_token' => 'UNIQ_E7B358EB6F568EC8',
        ],
        'mushroom_article_link' => [
            'idx_mushroom_article_mushroom' => 'IDX_BCC51ECBE3FCCFFC',
            'idx_mushroom_article_blog_post' => 'IDX_BCC51ECBA77FBEAF',
        ],
    ];

    /** Symfony Messenger 7.4: jeden spoločný index namiesto troch. */
    private const MESSENGER_OLD_INDEXES = ['IDX_75EA56E0FB7336F0', 'IDX_75EA56E0E3BD61CE', 'IDX_75EA56E016BA31DB'];
    private const MESSENGER_NEW_INDEX = 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750';

    public function getDescription(): string
    {
        return 'Zosúladenie schémy s entitami: názvy indexov, user.roles ako JSON, blog_post.slug bez default, index messenger_messages';
    }

    public function isTransactional(): bool
    {
        // ALTER TABLE v MySQL aj tak transakciu ukončí.
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach (self::INDEX_RENAMES as $tableName => $renames) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }
            $table = $schema->getTable($tableName);
            foreach ($renames as $old => $new) {
                $this->renameIndexIfNeeded($table, $old, $new);
            }
        }

        if ($schema->hasTable('user')) {
            $roles = $schema->getTable('user')->getColumn('roles');
            if ($roles->getType() !== Type::getType(Types::JSON) && $this->allRolesAreValidJson()) {
                $roles->setType(Type::getType(Types::JSON));
                // Pôvodná kolácia (utf8mb4_bin) z LONGTEXT — MySQL ju pri type JSON nepovolí.
                $roles->setPlatformOptions([]);
            }
        }

        if ($schema->hasTable('blog_post')) {
            $slug = $schema->getTable('blog_post')->getColumn('slug');
            if ($slug->getDefault() !== null) {
                $slug->setDefault(null);
            }
        }

        if ($schema->hasTable('messenger_messages')) {
            $table = $schema->getTable('messenger_messages');
            if (!$table->hasIndex(self::MESSENGER_NEW_INDEX)) {
                $table->addIndex(['queue_name', 'available_at', 'delivered_at', 'id'], self::MESSENGER_NEW_INDEX);
            }
            foreach (self::MESSENGER_OLD_INDEXES as $old) {
                if ($table->hasIndex($old)) {
                    $table->dropIndex($old);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::INDEX_RENAMES as $tableName => $renames) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }
            $table = $schema->getTable($tableName);
            foreach ($renames as $old => $new) {
                $this->renameIndexIfNeeded($table, $new, $old);
            }
        }

        if ($schema->hasTable('user')) {
            $schema->getTable('user')->getColumn('roles')->setType(Type::getType(Types::TEXT))->setPlatformOptions([]);
        }

        if ($schema->hasTable('blog_post')) {
            $schema->getTable('blog_post')->getColumn('slug')->setDefault('');
        }

        if ($schema->hasTable('messenger_messages')) {
            $table = $schema->getTable('messenger_messages');
            foreach (['IDX_75EA56E0FB7336F0' => ['queue_name'], 'IDX_75EA56E0E3BD61CE' => ['available_at'], 'IDX_75EA56E016BA31DB' => ['delivered_at']] as $name => $columns) {
                if (!$table->hasIndex($name)) {
                    $table->addIndex($columns, $name);
                }
            }
            if ($table->hasIndex(self::MESSENGER_NEW_INDEX)) {
                $table->dropIndex(self::MESSENGER_NEW_INDEX);
            }
        }
    }

    private function renameIndexIfNeeded(Table $table, string $old, string $new): void
    {
        if ($table->hasIndex($old) && !$table->hasIndex($new)) {
            $table->renameIndex($old, $new);
        }
    }

    /**
     * Prevod na JSON zlyhá, ak niektorá hodnota nie je platný JSON — vtedy ho radšej preskočíme,
     * aby nezhodil deploy (a nahlásime to).
     */
    private function allRolesAreValidJson(): bool
    {
        $invalid = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `user` WHERE JSON_VALID(roles) = 0');
        if ($invalid > 0) {
            $this->write(sprintf('<comment>user.roles: %d hodnôt nie je platný JSON — prevod na JSON preskočený.</comment>', $invalid));

            return false;
        }

        return true;
    }
}
