<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_170000_add_emoji_reactions extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%conversation_reaction}}', [
            'message_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'emoji' => $this->string(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[message_id]], [[user_id]], [[emoji]])',
            'FOREIGN KEY ([[message_id]]) REFERENCES {{%conversation_message}} ([[id]]) ON DELETE CASCADE',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);
        $this->ensureEmojiStorage();
        $this->createIndex('idx_conversation_reaction_message', '{{%conversation_reaction}}', ['message_id', 'created_at']);
    }

    private function ensureEmojiStorage(): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        $this->execute('ALTER TABLE {{%conversation_reaction}} MODIFY [[emoji]] VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    }

    public function safeDown(): bool
    {
        echo "Emoji reactions are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}
