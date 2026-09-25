<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

/** Generalizes reactions from posts to every likeable HumHub content record. */
final class m260925_010000_add_content_emoji_reactions extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%content_emoji_reaction}}', [
            'content_model' => $this->string(255)->notNull(),
            'content_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'emoji' => $this->string(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[content_model]], [[content_id]], [[user_id]], [[emoji]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);
        $this->ensureEmojiStorage();
        $this->createIndex('idx_content_emoji_reaction_content', '{{%content_emoji_reaction}}', ['content_model', 'content_id', 'created_at']);
        $this->db->createCommand(
            'INSERT INTO {{%content_emoji_reaction}} ([[content_model]], [[content_id]], [[user_id]], [[emoji]], [[created_at]]) '
            . 'SELECT :model, [[post_id]], [[user_id]], [[emoji]], [[created_at]] FROM {{%post_emoji_reaction}}',
            [':model' => 'humhub\\modules\\post\\models\\Post']
        )->execute();
    }

    private function ensureEmojiStorage(): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        $this->execute('ALTER TABLE {{%content_emoji_reaction}} MODIFY [[emoji]] VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    }

    public function safeDown(): bool
    {
        echo "Emoji reactions are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}
