<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_190000_add_post_emoji_reactions extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%post_emoji_reaction}}', [
            'post_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'emoji' => $this->string(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[post_id]], [[user_id]], [[emoji]])',
            'FOREIGN KEY ([[post_id]]) REFERENCES {{%post}} ([[id]]) ON DELETE CASCADE',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);
        $this->createIndex('idx_post_emoji_reaction_post', '{{%post_emoji_reaction}}', ['post_id', 'created_at']);
    }

    public function safeDown(): bool
    {
        echo "Emoji reactions are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}
